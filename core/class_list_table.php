<?php
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

/*
 *
 * The current class is just wrapper.
 * To use dataTables Overview,
 * You should create child class
 *
 */
class WPI_List_Table extends WP_List_Table {

  public $table_scope;

  /**
   * Setup options mostly.
   *
   * @todo Get list of displayed columns from options
   *
   */
  function __construct( $args = '' ) {

    $args = wp_parse_args( $args, array(
      'plural' => '',
      'iColumns' => 3,
      'per_page' => 20,
      'iDisplayStart' => 0,
      'ajax_action' => 'wpi_list_table', // Should be set in child class!
      'current_screen' => '',
      'table_scope' => '', // Should be set in child class!
      'singular' => '',
      'ajax' => false
    ) );

    $this->_args = $args;

    if ( empty( $this->_args[ 'current_screen' ] ) ) {
      if ( $this->_args[ 'ajax' ] != true ) {
        $screen = get_current_screen();
        $this->_args[ 'current_screen' ] = $screen->id;
      }
    }

    //* Returns columns, hidden, sortable */
    list( $columns, $hidden, $sortable ) = $this->get_column_info();

    //** Build aoColumns for ajax return */
    $column_count = 0;
    foreach ( $columns as $column_slug => $column_title ) {

      if ( key_exists( $column_slug, $sortable ) ) {
        $column_sortable = 'true';
      } else {
        $column_sortable = 'false';
      }

      $this->aoColumns[ ] = "{ 'sClass': '{$column_slug} column-{$column_slug}', 'bSortable':{$column_sortable} }";
      $this->aoColumnDefs[ ] = "{ 'sName': '{$column_slug}', 'aTargets': [{$column_count}]}";
      $this->column_ids[ $column_count ] = $column_slug;
      $column_count++;
    }

    $this->_args[ 'iColumns' ] = count( $this->aoColumns );
    $this->table_scope = $this->_args[ 'table_scope' ];
  }

  /**
   * Display the search box.
   *
   * @since 3.1.0
   * @access public
   *
   * @param string $text The search button text
   * @param string $input_id The search input id
   */
  function search_box( $text, $input_id ) {
    if ( empty( $_REQUEST[ 's' ] ) && !$this->has_items() ) {
      return;
    }

    $input_id = $input_id . '-search-input';
    ?>
    <p class="search-box">
      <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
      <input type="text" id="<?php echo $input_id ?>" name="wpi_search[s]" value="<?php _admin_search_query(); ?>"/>
      <?php /* submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); */ ?>
    </p>
  <?php
  }

  /**
   * Whether the table has items to display or not
   *
   */
  function has_items() {
    return !empty( $this->all_items );
  }

  /**
   * Initialize the DataTable View
   *
   */
  function data_tables_script( $args = '' ) {
    ?>
    <script type="text/javascript">
      var wp_list_table;
      var wp_table_column_ids = {}
      <?php foreach($this->column_ids as $col_id => $col_slug) : ?>
      wp_table_column_ids['<?php echo $col_slug; ?>'] = '<?php echo $col_id; ?>';
      <?php endforeach; ?>

      jQuery( document ).ready( function () {
        /* Initialize the dataTable */
        wp_list_table = jQuery( "#wp-list-table" ).dataTable( {
          "sPaginationType": "full_numbers",
          "sDom": 'prtpl',
          "iDisplayLength": <?php echo $this->_args['per_page']; ?>,
          "bAutoWidth": false,
          "oLanguage": {
            "sLengthMenu": '<?php _e('Display', WPI) ?> <select><option value="25">25 </option><option value="50">50 </option><option value="100">100</option><option value="-1"><?php _e('All', WPI) ?> </option></select> <?php _e('records', WPI) ?>',
            "sProcessing": '<div class="ajax_loader_overview"></div>'
          },
          "iColumns": <?php echo count($this->aoColumnDefs); ?>,
          "bProcessing": true,
          "bServerSide": true,
          "aoColumnDefs": [<?php echo implode(',', $this->aoColumnDefs); ?>],
          "sAjaxSource": ajaxurl + '?&action=<?php echo $this->_args['ajax_action']; ?>',
          "fnServerData": function ( sSource, aoData, fnCallback ) {
            aoData.push( {
              name: 'wpi_filter_vars',
              value: jQuery( '#<?php echo $this->table_scope; ?>-filter' ).serialize()
            } );
            jQuery.ajax( {
              "dataType": 'json',
              "type": "POST",
              "url": sSource,
              "data": aoData,
              "success": fnCallback
            } );
          },
          "aoColumns": [<?php echo implode(",", $this->aoColumns); ?>],
          "fnDrawCallback": function () {
            wp_list_table_do_columns();
          }
        } );

        /* Search by Filter */
        jQuery( "#<?php echo $this->table_scope; ?>-filter #search-submit" ).click( function ( event ) {
          event.preventDefault();
          wp_list_table.fnDraw();
          return false;
        } );

        jQuery( '.metabox-prefs' ).change( function () {
          wp_list_table_do_columns();
        } );
      } );

      //** Check which columns are hidden, and hide data table columns */
      function wp_list_table_do_columns () {
        // Hide any "hidden" columns from table
        var visible_columns = jQuery( '.hide-column-tog' ).filter( ':checked' ).map( function () {
          return jQuery( this ).val();
        } );
        var hidden_columns = jQuery( '.hide-column-tog' ).filter( ':not(:checked)' ).map( function () {
          return jQuery( this ).val();
        } );

        jQuery.each( hidden_columns, function ( key, row_class ) {
          jQuery( '#wp-list-table .' + row_class ).hide();
        } );
        jQuery.each( visible_columns, function ( key, row_class ) {
          jQuery( '#wp-list-table .' + row_class ).show();
        } );
      }
    </script>
  <?php
  }

  /**
   * Get a list of all, hidden and sortable columns, with filter applied
   *
   * @since 3.1.0
   * @access protected
   *
   * @return array
   */
  function get_column_info() {
    if ( isset( $this->_column_headers ) ) {
      return $this->_column_headers;
    }

    $screen = convert_to_screen( $this->_args[ 'current_screen' ] );

    $columns = get_column_headers( $screen );

    $hidden = get_hidden_columns( $screen );

    $_sortable = apply_filters( "manage_{$screen->id}_sortable_columns", $this->get_sortable_columns() );

    $sortable = array();
    foreach ( $_sortable as $id => $data ) {
      if ( empty( $data ) )
        continue;

      $data = (array) $data;
      if ( !isset( $data[ 1 ] ) )
        $data[ 1 ] = false;

      $sortable[ $id ] = $data;
    }

    $this->_column_headers = array( $columns, $hidden, $sortable );

    return $this->_column_headers;
  }

  /**
   * Get search results based on query.
   *
   * @todo Needs to be updated to handle the AJAX requests.
   *
   */
  function prepare_items( $wpi_search = false ) {

    if ( !isset( $this->all_items ) ) {
      $this->all_items = WPI_Functions::query( $wpi_search );
    }

    //** Do pagination  */
    if ( !empty( $this->all_items ) && $this->_args[ 'per_page' ] != -1 ) {
      $this->item_pages = array_chunk( $this->all_items, $this->_args[ 'per_page' ] );

      $total_chunks = count( $this->item_pages );

      //** figure out what page chunk we are on based on iDisplayStart
      $this_chunk = ( $this->_args[ 'iDisplayStart' ] / $this->_args[ 'per_page' ] );

      //** Get page items */
      $this->items = $this->item_pages[ $this_chunk ];

    } else {
      $this->items = $this->all_items;
    }
  }

  /**
   * Generate the table navigation above or below the table
   *
   * @since 3.1.0
   * @access protected
   */
  function display_tablenav( $which ) {
    if ( 'top' == $which ) {
      wp_nonce_field( 'bulk-' . $this->_args[ 'plural' ] );
    }
    /* Get Bulk actions HTML */
    ob_start();
    $this->bulk_actions();
    $bulk_actions = ob_get_contents();
    ob_end_clean();

    /* If bulk actions exists, - show them */
    if ( !empty( $bulk_actions ) ) {
      ?>
      <div class="tablenav <?php echo esc_attr( $which ); ?>">
        <div class="alignleft actions">
          <?php echo $bulk_actions; ?>
        </div>
        <br class="clear"/>
      </div>
    <?php
    }
  }

  /**
   * Display a monthly dropdown for filtering items
   *
   * @since 3.1.0
   * @access protected
   */
  function months_dropdown( $post_type, $field_name = 'm', $return = false ) {
    global $wpdb, $wp_locale;

    $months = $wpdb->get_results( $wpdb->prepare( "
      SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
      FROM $wpdb->posts
      WHERE post_type = %s
      ORDER BY post_date DESC
    ", $post_type ) );

    $month_count = count( $months );

    if ( !$month_count || ( 1 == $month_count && 0 == $months[ 0 ]->month ) ) {
      return false;
    }

    $m = isset( $_GET[ 'm' ] ) ? (int) $_GET[ 'm' ] : 0;

    ob_start();

    ?>
    <select name="<?php echo $field_name; ?>">
      <option<?php selected( $m, 0 ); ?> value='0'><?php _e( 'Show all dates', WPI ); ?></option>
      <?php
      foreach ( $months as $arc_row ) {
        if ( 0 == $arc_row->year ) {
          continue;
        }

        $month = zeroise( $arc_row->month, 2 );
        $year = $arc_row->year;

        printf( "<option %s value='%s'>%s</option>\n",
          selected( $m, $year . $month, false ),
          esc_attr( $arc_row->year . $month ),
          $wp_locale->get_month( $month ) . " $year"
        );
      }
      ?>
    </select>
    <?php

    $content = ob_get_contents();
    ob_end_clean();

    if ( $return ) {
      return $content;
    } else {
      echo $content;
    }

  }

  function display_rows() {
    foreach ( $this->items as $userid => $object ) {
      echo "\n\t", $this->single_row( $object );
    }
  }

  /**
   * Display the table
   *
   * @since 3.1.0
   * @access public
   */
  function display( $args = '' ) {

    /* Display Bulk Actions if exist */
    $this->display_tablenav( 'top' );
    ?>
    <div class="wpi_above_overview_table"></div>
    <table id="wp-list-table" class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>"
           cellspacing="0">
      <thead>
      <tr>
        <?php $this->print_column_headers(); ?>
      </tr>
      </thead>

      <tfoot>
      <tr>
        <?php $this->print_column_headers( false ); ?>
      </tr>
      </tfoot>

      <tbody id="the-list">
      <?php $this->display_rows_or_placeholder(); ?>
      </tbody>
    </table>
  <?php
  }

  /**
   * Print column headers, accounting for hidden and sortable columns.
   *
   * @since 3.1.0
   * @access protected
   *
   * @param bool $with_id Whether to set the id attribute or not
   */
  function print_column_headers( $with_id = true ) {
    $screen = get_current_screen();

    list( $columns, $hidden, $sortable ) = $this->get_column_info();

    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];
    $current_url = remove_query_arg( 'paged', $current_url );

    if ( isset( $_GET[ 'orderby' ] ) )
      $current_orderby = $_GET[ 'orderby' ];
    else
      $current_orderby = '';

    if ( isset( $_GET[ 'order' ] ) && 'desc' == $_GET[ 'order' ] )
      $current_order = 'desc';
    else
      $current_order = 'asc';

    foreach ( $columns as $column_key => $column_display_name ) {
      $class = array( 'manage-column', "column-$column_key" );

      $style = '';
      if ( in_array( $column_key, $hidden ) )
        $style = 'display:none;';

      $style = ' style="' . $style . '"';

      if ( 'cb' == $column_key )
        $class[ ] = 'check-column';
      elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) )
        $class[ ] = 'num';

      if ( isset( $sortable[ $column_key ] ) ) {
        list( $orderby, $desc_first ) = $sortable[ $column_key ];

        if ( $current_orderby == $orderby ) {
          $order = 'asc' == $current_order ? 'desc' : 'asc';
          $class[ ] = 'sorted';
          $class[ ] = $current_order;
        } else {
          $order = $desc_first ? 'desc' : 'asc';
          $class[ ] = 'sortable';
          $class[ ] = $desc_first ? 'asc' : 'desc';
        }

        $column_display_name = '<a href="' . esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';

      }

      $id = $with_id ? "id='$column_key'" : '';

      if ( !empty( $class ) )
        $class = "class='" . join( ' ', $class ) . "'";

      if ( 'cb' == $column_key )
        $column_display_name = '<input type="checkbox" class="check-all" />';

      echo "<th scope='col' $id $class $style>$column_display_name</th>";
    }
  }

  function no_items() {
    //** DataTables expects a set number of columns */
    $result[ 0 ] = '';
    $result[ 1 ] = __( 'Nothing found.' );

    if ( count( $result ) < $this->_args[ 'iColumns' ] ) {
      $add_columns = ( $this->_args[ 'iColumns' ] - count( $result ) );
      //** Add some blank rows to not break json result array */
      $i = 1;
      while ( $i <= $add_columns ) {
        $result[ ] = '';
        $i++;
      }
    }
    return $result;
  }

  /**
   * Generate HTML for a single row on the users.php admin panel.
   *
   */
  function single_row( $object ) {
    global $wpi;

    $data = array(
      'table_scope' => $this->_args[ 'table_scope' ],
      'object' => $object
    );

    $object = (array) $object;

    $object_id = $object[ 'ID' ];

    $r = "<tr id='object-$object_id' class='wpi_parent_element'>";

    list( $columns, $hidden ) = $this->get_column_info();

    foreach ( $columns as $column_name => $column_display_name ) {
      $class = "class=\"$column_name column-$column_name\"";
      $style = '';

      if ( in_array( $column_name, $hidden ) ) {
        $style = ' style="display:none;"';
      }

      $attributes = "$class$style";

      $r .= "<td {$attributes}>";

      $single_cell = $this->single_cell( $column_name, $object, $object_id );

      //** Need to insert some sort of space in there to avoid DataTable error that occures when "null" is returned */
      $ajax_cells[ ] = $single_cell;

      $r .= $single_cell;
      $r .= "</td>";
    }

    $r .= '</tr>';

    if ( $this->_args[ 'ajax' ] ) {
      return $ajax_cells;
    }

    return $r;
  }

  /**
   * Keep it simple here.  Mostly to be either replaced by child classes, or hook into
   *
   */
  function single_cell( $full_column_name, $object, $object_id ) {
    global $wpi;

    $object = (array) $object;

    $column_name = str_replace( 'wpi_', '', $full_column_name );

    $cell_data = array(
      'table_scope' => $this->_args[ 'table_scope' ],
      'column_name' => $column_name,
      'object_id' => $object_id,
      'object' => $object,
      'wpi_list_table' => $this
    );

    $value = ( isset( $object[ $column_name ] ) ) ? $object[ $column_name ] : "";

    $r = apply_filters( $this->_args[ 'table_scope' ] . '_table_cell', $value, $cell_data );

    return $r;
  }

  /**
	 * Display the bulk actions dropdown.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	function bulk_actions() {
      if ( is_null( $this->_actions ) ) {
          $no_new_actions = $this->_actions = $this->get_bulk_actions();
          /**
           * Filter the list table Bulk Actions drop-down.
           *
           * The dynamic portion of the hook name, $this->screen->id, refers
           * to the ID of the current screen, usually a string.
           *
           * This filter can currently only be used to remove bulk actions.
           *
           * @since 3.5.0
           *
           * @param array $actions An array of the available bulk actions.
           */
          $this->_actions = apply_filters( "bulk_actions-".get_current_screen()->id, $this->_actions );
          $this->_actions = array_intersect_assoc( $this->_actions, $no_new_actions );
          $two = '';
      } else {
          $two = '2';
      }

      if ( empty( $this->_actions ) )
          return;

      echo "<select name='action$two'>\n";
      echo "<option value='-1' selected='selected'>" . __( 'Bulk Actions' ) . "</option>\n";

      foreach ( $this->_actions as $name => $title ) {
          $class = 'edit' == $name ? ' class="hide-if-no-js"' : '';

          echo "\t<option value='$name'$class>$title</option>\n";
      }

      echo "</select>\n";

      submit_button( __( 'Apply' ), 'action', false, false, array( 'id' => "doaction$two" ) );
      echo "\n";
	}

}