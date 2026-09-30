<?php
/**
 * Logged-in writer workspace shortcode.
 *
 * @package KABITAB_Story_System
 */

defined( 'ABSPATH' ) || exit;

class Kabitab_Writer_Dashboard {
	public static function register() {
		add_shortcode( 'kabitab_writer_dashboard', array( __CLASS__, 'render' ) );
	}

	public static function ensure_writer_role() {
		Kabitab_Writer_Workflow::ensure_writer_role();
	}

	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}
		global $post;
		if ( ! $post instanceof WP_Post || ! has_shortcode( (string) $post->post_content, 'kabitab_writer_dashboard' ) ) {
			return;
		}
		$stylesheet = KABITAB_STORY_SYSTEM_PATH . 'assets/css/writer-dashboard.css';
		if ( file_exists( $stylesheet ) ) {
			wp_enqueue_style( 'kabitab-writer-dashboard', KABITAB_STORY_SYSTEM_URL . 'assets/css/writer-dashboard.css', array(), (string) filemtime( $stylesheet ) );
		}
	}

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return self::notice( __( 'Sign in to access your writing desk.', 'kabitab-story-system' ), wp_login_url( get_permalink() ), __( 'Sign in', 'kabitab-story-system' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return self::notice( __( 'Your account does not yet have Writer access. Please contact an editor.', 'kabitab-story-system' ) );
		}

		$requested_form = Kabitab_Writer_Workflow::render_requested_form();
		if ( '' !== $requested_form ) {
			return $requested_form;
		}

		$user_id        = get_current_user_id();
		$can_manage_all = current_user_can( 'edit_others_posts' );
		$story_query    = array(
				'post_type'      => 'kabitab_story',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			);
		if ( ! $can_manage_all ) {
			$story_query['author'] = $user_id;
		}
		$stories = get_posts( $story_query );
		$published_stories = 0;
		$total_chapters    = 0;
		foreach ( $stories as $story ) {
			if ( 'publish' === $story->post_status ) {
				++$published_stories;
			}
			$chapter_query = array(
						'post_type'      => 'kabitab_chapter',
						'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_key'       => '_kabitab_parent_story',
						'meta_value'     => $story->ID,
						'no_found_rows'  => true,
					);
			if ( ! $can_manage_all ) {
				$chapter_query['author'] = $user_id;
			}
			$total_chapters += count( get_posts( $chapter_query ) );
		}
		$public_name = $can_manage_all ? wp_get_current_user()->display_name : ( ! empty( $stories ) ? Kabitab_Frontend::get_author_label( $stories[0]->ID ) : wp_get_current_user()->display_name );
		$desk_label  = $can_manage_all ? __( 'Editorial dashboard', 'kabitab-story-system' ) : __( 'Writer workspace', 'kabitab-story-system' );
		$desk_intro  = $can_manage_all ? __( 'See every KABITAB story and open the exact writing or editorial task that needs attention.', 'kabitab-story-system' ) : __( 'Create your own story, upload your source material, and keep your chapters ready for readers.', 'kabitab-story-system' );

		ob_start();
		?>
		<section class="kabitab-writer-dashboard" aria-labelledby="kabitab-writer-dashboard-title">
			<header class="kabitab-writer-dashboard__header">
				<div>
					<p class="kabitab-writer-dashboard__eyebrow"><?php echo esc_html( $desk_label ); ?></p>
					<h2 id="kabitab-writer-dashboard-title"><?php echo esc_html( sprintf( __( 'Welcome, %s', 'kabitab-story-system' ), $public_name ) ); ?></h2>
					<p><?php echo esc_html( $desk_intro ); ?></p>
				</div>
				<a class="kabitab-writer-dashboard__primary-action" href="<?php echo esc_url( Kabitab_Writer_Workflow::dashboard_url( array( 'writer_action' => 'new_story' ) ) ); ?>"><?php esc_html_e( 'Create or upload a story', 'kabitab-story-system' ); ?></a>
			</header>
			<?php echo Kabitab_Writer_Workflow::current_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="kabitab-writer-dashboard__stats" aria-label="<?php esc_attr_e( 'Writing totals', 'kabitab-story-system' ); ?>">
				<span><strong><?php echo esc_html( count( $stories ) ); ?></strong><?php esc_html_e( 'Total stories', 'kabitab-story-system' ); ?></span>
				<span><strong><?php echo esc_html( $published_stories ); ?></strong><?php esc_html_e( 'Published', 'kabitab-story-system' ); ?></span>
				<span><strong><?php echo esc_html( count( $stories ) - $published_stories ); ?></strong><?php esc_html_e( 'In progress', 'kabitab-story-system' ); ?></span>
				<span><strong><?php echo esc_html( $total_chapters ); ?></strong><?php esc_html_e( 'Chapters', 'kabitab-story-system' ); ?></span>
			</div>
			<?php if ( ! $stories ) : ?>
				<p class="kabitab-writer-dashboard__empty"><?php esc_html_e( 'Your writing desk is ready. Start your first story when you are ready.', 'kabitab-story-system' ); ?></p>
			<?php else : ?>
				<div class="kabitab-writer-dashboard__list">
					<?php foreach ( $stories as $story ) : self::render_story( $story, $can_manage_all ); endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	private static function render_story( $story, $can_manage_all = false ) {
		$story_id  = $story->ID;
		$chapter_query = array(
				'post_type'      => 'kabitab_chapter',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_kabitab_parent_story',
						'value' => $story_id,
					),
				),
				'orderby'        => array( 'meta_value_num' => 'ASC', 'modified' => 'DESC' ),
				'meta_key'       => '_kabitab_chapter_number',
				'no_found_rows'  => true,
			);
		if ( ! $can_manage_all ) {
			$chapter_query['author'] = get_current_user_id();
		}
		$chapters  = get_posts( $chapter_query );
		$status    = get_post_status_object( $story->post_status );
		$edit_url = Kabitab_Writer_Workflow::dashboard_url( array( 'writer_action' => 'edit_story', 'story_id' => $story_id ) );
		$new_chapter_url = Kabitab_Writer_Workflow::dashboard_url( array( 'writer_action' => 'new_chapter', 'story_id' => $story_id ) );
		$has_cover   = has_post_thumbnail( $story_id );
		$has_summary = '' !== trim( (string) get_post_meta( $story_id, '_kabitab_blurb', true ) );
		$narrated_chapters = 0;
		foreach ( $chapters as $chapter ) {
			if ( ! empty( wp_parse_id_list( get_post_meta( $chapter->ID, '_kabitab_narration_ids', true ) ) ) ) {
				++$narrated_chapters;
			}
		}
		$view_url    = 'publish' === $story->post_status ? get_permalink( $story_id ) : get_preview_post_link( $story_id );
		?>
		<article class="kabitab-writer-dashboard__story">
			<div class="kabitab-writer-dashboard__story-top">
				<div class="kabitab-writer-dashboard__story-main">
					<p class="kabitab-writer-dashboard__story-status kabitab-writer-dashboard__story-status--<?php echo esc_attr( $story->post_status ); ?>"><?php echo esc_html( $status ? $status->label : __( 'Unknown', 'kabitab-story-system' ) ); ?></p>
					<h3><?php echo esc_html( get_the_title( $story ) ); ?></h3>
					<?php if ( $can_manage_all ) : ?><p class="kabitab-writer-dashboard__story-owner"><?php echo esc_html( sprintf( __( 'Writer: %s', 'kabitab-story-system' ), Kabitab_Frontend::get_author_label( $story_id ) ) ); ?></p><?php endif; ?>
					<p><?php echo esc_html( sprintf( __( '%1$d chapters · Updated %2$s', 'kabitab-story-system' ), count( $chapters ), get_the_modified_date( '', $story ) ) ); ?></p>
				</div>
				<div class="kabitab-writer-dashboard__actions">
					<?php if ( $view_url ) : ?><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( 'publish' === $story->post_status ? __( 'View story', 'kabitab-story-system' ) : __( 'Preview', 'kabitab-story-system' ) ); ?></a><?php endif; ?>
					<?php if ( $edit_url ) : ?><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit story', 'kabitab-story-system' ); ?></a><?php endif; ?>
					<a class="is-primary" href="<?php echo esc_url( $new_chapter_url ); ?>"><?php esc_html_e( 'Add chapter', 'kabitab-story-system' ); ?></a>
				</div>
			</div>
			<div class="kabitab-writer-dashboard__readiness" aria-label="<?php esc_attr_e( 'Story readiness', 'kabitab-story-system' ); ?>">
				<span class="<?php echo $has_cover ? 'is-ready' : 'needs-work'; ?>"><?php echo esc_html( $has_cover ? __( 'Cover ready', 'kabitab-story-system' ) : __( 'Add a cover', 'kabitab-story-system' ) ); ?></span>
				<span class="<?php echo $has_summary ? 'is-ready' : 'needs-work'; ?>"><?php echo esc_html( $has_summary ? __( 'Summary ready', 'kabitab-story-system' ) : __( 'Add a summary', 'kabitab-story-system' ) ); ?></span>
				<span class="<?php echo ! empty( $chapters ) ? 'is-ready' : 'needs-work'; ?>"><?php echo esc_html( ! empty( $chapters ) ? __( 'Chapter added', 'kabitab-story-system' ) : __( 'Add the first chapter', 'kabitab-story-system' ) ); ?></span>
				<span class="<?php echo $narrated_chapters ? 'is-ready' : 'needs-work'; ?>"><?php echo esc_html( sprintf( _n( '%1$d narration-ready chapter', '%1$d/%2$d narration-ready chapters', count( $chapters ), 'kabitab-story-system' ), $narrated_chapters, count( $chapters ) ) ); ?></span>
			</div>
			<?php if ( ! empty( $chapters ) ) : ?>
				<ol class="kabitab-writer-dashboard__chapters">
					<?php foreach ( $chapters as $chapter ) : ?>
						<?php $chapter_status = get_post_status_object( $chapter->post_status ); ?>
						<li>
							<div><span><?php echo esc_html( sprintf( __( 'Part %d', 'kabitab-story-system' ), kabitab_sanitize_positive_integer( get_post_meta( $chapter->ID, '_kabitab_chapter_number', true ) ) ) ); ?></span><strong><?php echo esc_html( get_the_title( $chapter ) ); ?></strong></div>
							<small><?php echo esc_html( $chapter_status ? $chapter_status->label : __( 'Unknown', 'kabitab-story-system' ) ); ?></small>
							<a href="<?php echo esc_url( Kabitab_Writer_Workflow::dashboard_url( array( 'writer_action' => 'edit_chapter', 'chapter_id' => $chapter->ID ) ) ); ?>"><?php esc_html_e( 'Edit / audio', 'kabitab-story-system' ); ?></a>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</article>
		<?php
	}

	private static function notice( $message, $url = '', $link_label = '' ) {
		$html = '<section class="kabitab-writer-dashboard kabitab-writer-dashboard--notice"><p>' . esc_html( $message ) . '</p>';
		if ( $url && $link_label ) {
			$html .= '<a class="kabitab-writer-dashboard__primary-action" href="' . esc_url( $url ) . '">' . esc_html( $link_label ) . '</a>';
		}
		return $html . '</section>';
	}
}
