<?php

namespace RevisionsExtended\Admin;

use WP_List_Table, WP_Post, WP_Query;
use function RevisionsExtended\Admin\get_subpage_url;
use function RevisionsExtended\Post_Status\get_revision_statuses;

defined( 'WPINC' ) || die();

/**
 * Class Revision_List_Table
 *
 * A list table for Scheduled Updates revisions.
 */
class Revision_List_Table extends WP_List_Table {
	/**
	 * @var string
	 */
	public $parent_post_type;

	/**
	 * Revision_List_Table constructor.
	 *
	 * @param array $args
	 */
	public function __construct( $args = array() ) {
		global $typenow;
		$this->parent_post_type = $typenow ?: 'post';

		parent::__construct( $args );
	}

	/**
	 * Checks the current user's permissions
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( get_post_type_object( $this->parent_post_type )->cap->edit_posts );
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @uses WP_List_Table::set_pagination_args()
	 *
	 * @return void
	 */
	public function prepare_items() {
		$post_type = 'revision';
		$per_page  = $this->get_items_per_page( 'edit_' . $post_type . '_per_page' );

		/** This filter is documented in wp-admin/includes/post.php */
		$per_page = apply_filters( 'edit_posts_per_page', $per_page, $post_type );

		$orderby   = wp_unslash( filter_input( INPUT_GET, 'orderby' ) );
		$order     = wp_unslash( filter_input( INPUT_GET, 'order' ) );
		$search    = wp_unslash( filter_input( INPUT_GET, 's' ) );
		$parent_id = filter_input( INPUT_GET, 'p', FILTER_VALIDATE_INT );

		$query_args = array(
			'post_type'      => 'revision',
			'post_status'    => 'future',
			'posts_per_page' => $per_page,
			'orderby'        => $orderby ?: 'date ID',
			'order'          => $order ?: 'asc',
		);

		if ( $search ) {
			$query_args['s'] = $search;
		}

		if ( $parent_id ) {
			$query_args['post_parent'] = $parent_id;
		}

		$query = new WP_Query( $query_args );

		$this->items = $query->get_posts();

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Gets the list of views available on this table.
	 *
	 * The format is an associative array:
	 * - `'id' => 'link'`
	 *
	 * @return array
	 */
	protected function get_views() {
		$view_links = array();

		$revision_statuses = get_revision_statuses();
		$posts_by_status   = (array) wp_count_posts( 'revision' );
		$total_posts       = array_sum( array_intersect_key( $posts_by_status, $revision_statuses ) );

		$all_inner_html = sprintf(
			/* translators: %s: Number of posts. */
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts',
				'revisions-extended'
			),
			number_format_i18n( $total_posts )
		);

		$view_links['all'] = sprintf(
			'<a href="%1$s"%2$s%3$s>%4$s</a>',
			esc_url( get_subpage_url( $this->parent_post_type ) ),
			' class="current"',
			' aria-current="page"',
			$all_inner_html
		);

		if ( count( $view_links ) > 1 ) {
			return $view_links;
		}

		return array();
	}

	/**
	 * Retrieves the list of bulk actions available for this table.
	 *
	 * The format is an associative array where each element represents either a top level option value and label, or
	 * an array representing an optgroup and its options.
	 *
	 * For a standard option, the array element key is the field value and the array element value is the field label.
	 *
	 * For an optgroup, the array element key is the label and the array element value is an associative array of
	 * options as above.
	 *
	 * Example:
	 *
	 *     [
	 *         'edit'         => 'Edit',
	 *         'delete'       => 'Delete',
	 *         'Change State' => [
	 *             'feature' => 'Featured',
	 *             'sale'    => 'On Sale',
	 *         ]
	 *     ]
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'revisions-extended' ),
		);

		return $actions;
	}

	/**
	 * Gets a list of columns.
	 *
	 * The format is:
	 * - `'internal-name' => 'Title'`
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'        => '<input type="checkbox" />',
			'title'     => _x( 'Title', 'column name', 'revisions-extended' ),
			'author'    => _x( 'Author', 'column name', 'revisions-extended' ),
			'parent'    => _x( 'An update to', 'column name', 'revisions-extended' ),
			'scheduled' => _x( 'Scheduled for', 'column name', 'revisions-extended' ),
			'modified'  => _x( 'Modified on', 'column name', 'revisions-extended' ),
		);

		$parent_id = filter_input( INPUT_GET, 'p', FILTER_VALIDATE_INT );
		if ( $parent_id ) {
			unset( $columns['parent'] );
		}

		return $columns;
	}

	/**
	 * Gets a list of sortable columns.
	 *
	 * The format is:
	 * - `'internal-name' => 'orderby'`
	 * - `'internal-name' => array( 'orderby', 'asc' )` - The second element sets the initial sorting order.
	 * - `'internal-name' => array( 'orderby', true )`  - The second element makes the initial order descending.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'title'     => 'title',
			'scheduled' => array( 'date', 'asc' ),
			'modified'  => array( 'modified', true ),
		);
	}

	/**
	 * Render the checkbox ("cb") column.
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function column_cb( $post ) {
		$show = current_user_can( 'edit_post', $post->ID );

		if ( $show ) :
			?>
			<label class="screen-reader-text" for="cb-select-<?php echo esc_attr( $post->ID ); ?>">
				<?php
				/* translators: %s: Post title. */
				printf( __( 'Select %s' ), _draft_or_post_title() );
				?>
			</label>
			<input
				id="cb-select-<?php echo esc_attr( $post->ID ); ?>"
				type="checkbox"
				name="bulk_edit[]"
				value="<?php echo esc_attr( $post->ID ); ?>"
			/>
			<div class="locked-indicator">
				<span class="locked-indicator-icon" aria-hidden="true"></span>
				<span class="screen-reader-text">
				<?php
				printf(
				/* translators: %s: Post title. */
					__( '&#8220;%s&#8221; is locked' ),
					_draft_or_post_title( $post )
				);
				?>
				</span>
			</div>
		<?php
		endif;
	}

	/**
	 * Render the Title column.
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function column_title( $post ) {
		// Actually checks the parent post.
		$can_edit_post = current_user_can( 'edit_post', $post->ID );

		if ( $can_edit_post ) {
			$lock_holder = wp_check_post_lock( $post->ID );

			if ( $lock_holder ) {
				$lock_holder   = get_userdata( $lock_holder );
				$locked_avatar = get_avatar( $lock_holder->ID, 18 );
				/* translators: %s: User's display name. */
				$locked_text = esc_html( sprintf( __( '%s is currently editing' ), $lock_holder->display_name ) );
			} else {
				$locked_avatar = '';
				$locked_text   = '';
			}

			echo '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . $locked_text . "</span></div>\n";
		}

		echo '<strong>';

		$title = _draft_or_post_title( $post );

		if ( $can_edit_post ) {
			$edit_url = add_query_arg(
				array(
					'post'   => $post->ID,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			);

			printf(
				'<a class="row-title" href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( $edit_url ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) ),
				$title
			);
		} else {
			printf(
				'<span>%s</span>',
				$title
			);
		}

		_post_states( $post );

		echo "</strong>\n";

		get_inline_data( $post );
	}

	/**
	 * Render the Author column.
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function column_author( $post ) {
		echo get_the_author_meta( 'nicename', $post->post_author );
	}

	/**
	 * Render the Parent column.
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function column_parent( $post ) {
		$parent = get_post( $post->post_parent );

		if ( ! $parent ) {
			esc_html_e( 'Error: No parent.', 'revisions-extended' );

			return;
		}

		// Actually checks the parent post.
		$can_edit_post = current_user_can( 'edit_post', $post->ID );
		$title         = _draft_or_post_title( $parent );

		if ( $can_edit_post ) {
			$edit_url = add_query_arg(
				array(
					'post'   => $parent->ID,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			);

			printf(
				'<a class="row-title" href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( $edit_url ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) ),
				$title
			);
		} else {
			printf(
				'<span>%s</span>',
				$title
			);
		}

		$parent_post_type_object = get_post_type_object( get_post_type( $parent ) );

		printf(
			'<a href="%1$s" class="view-item-link">%2$s</a>',
			esc_url( get_permalink( $parent->ID ) ),
			esc_html( $parent_post_type_object->labels->view_item )
		);
	}

	/**
	 * Render the Scheduled column.
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function column_scheduled( $post ) {
		printf(
			/* translators: 1: Post date, 2: Post time. */
			__( '%1$s at %2$s', 'revisions-extended' ),
			get_the_date( get_option( 'date_format', 'Y/m/d' ), $post ),
			get_the_time( get_option( 'time_format', 'g:i a' ), $post )
		);
	}

	/**
	 * Render the Modified column.
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function column_modified( $post ) {
		printf(
			/* translators: 1: Post date, 2: Post time. */
			__( '%1$s at %2$s', 'revisions-extended' ),
			get_the_modified_date( get_option( 'date_format', 'Y/m/d' ), $post ),
			get_the_modified_time( get_option( 'time_format', 'g:i a' ), $post )
		);
	}

	/**
	 * Generates and display row actions links for the list table.
	 *
	 * @param object|array $post        The post being acted upon.
	 * @param string       $column_name Current column name.
	 * @param string       $primary     Primary column name.
	 *
	 * @return string The row actions HTML, or an empty string
	 *                if the current column is not the primary column.
	 */
	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post    = current_user_can( 'edit_post', $post->ID );
		$actions          = array();
		$title            = _draft_or_post_title( $post );

		// Edit.
		if ( $can_edit_post ) {
			$edit_url = add_query_arg(
				array(
					'post'   => $post->ID,
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			);

			$actions['edit'] = sprintf(
				'<a href="%1$s" aria-label="%2$s">%3$s</a>',
				esc_url( $edit_url ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'revisions-extended' ), $title ) ),
				__( 'Edit', 'revisions-extended' )
			);
		}

		// Compare.
		// TODO

		// Publish.
		// TODO

		// Delete.
		// TODO

		return $this->row_actions( $actions );
	}
}
