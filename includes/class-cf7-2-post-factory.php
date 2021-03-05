<?php
class CF72Post_Mapping_Factory {
  /**
	 * cache of Form_2_Post_Mapper objects for loaded forms.
	 *
	 * @since    5.0.0
	 * @access    protected
	 * @var      array    $post_mappers    an array of Form_2_Post_Mapper objects..
	 */
  protected $post_mappers;

  /**
	 * track post types mapped to create and add dashboard functionality.
	 *
	 * @since    5.0.0
	 * @access    protected
	 * @var      array    $mapped_post_types    an array of fomr IDs=>post types..
	 */
  protected static $mapped_post_types;
  /**
	 * Factory object.
	 *
	 * @since    1.0.0
	 * @access    protected
	 * @var      CF72Post_Mapping_Factory  $factory object instance of this class.
	 */
  protected static $factory;
  /**
   * Default Construct a Cf7_2_Post_Factory object.
   *
   * @since    1.0.0
   * @param    int    $cf7_post_id the ID of the CF7 form.
   */
  protected function __construct(){
    $this->post_mappers = array();
    if(is_admin()) $this->get_system_posts(); //only used in dashboard.

  }
  protected static function get_factory(){
    if(!isset(self::$factory)) self::$factory = new self();
    return self::$factory;
  }
  /**
  * set system posts
  *
  *@since 5.0.0
  *@return Array associative array of system post_types=>post label.
  */
  protected function get_system_posts(){
    if(!is_admin()) return false;

    $args = array(
     'show_ui'   => true
    );
    $post_types = get_post_types( $args, 'objects', 'and' );
    $html = '';
    $display = array();
    foreach($post_types as $post_type){
      switch($post_type->name){
        case 'wp_block':
        case 'wpcf7_contact_form':
          break;
        default:
          $display[$post_type->name] = $post_type->label;
          break;
      }
    }
    /**
    * add/remove system posts to which to map forms to. By defualt the plugin only lists system posts which are visible in the dashboard
    * @since 2.0.0
    * @param array $display  list of system post picked up by the plugin to display
    * @param string $form_id  the post id of the cf7 form currently being mapped
    * @return array an array of post-types=>post-label key value pairs to display
    */
    return apply_filters('cf7_2_post_display_system_posts', $display, $this->cf7_post_ID);
  }
  /**
   *Get a list of available system post_types as <option> elements
   *
   * @since 1.3.0
   * @return     String    html list of <option> elements with existing post_types in the DB
  **/
  public static function get_system_posts_options($selected){
    $factory = get_factory();
    $system_pt = $factory->get_system_posts();
    if(!isset($system_pt[$selected])) $selected = 'post';

    $html='';
    //debug_msg($display);
    foreach($system_pt as $post_type=>$post_label){
      $select = ($selected == $post_type) ? ' selected="true"':'';
      $html .='<option value="' . $post_type . '"' . $select . '>';
      $html .= $post_label . ' ('.$post_type.')';
      $html .='</option>' . PHP_EOL;
    }
    return $html;
  }
  /**
	 * Get a factory object for a CF7 form.
	 *
	 * @since    5.0.0
   * @param  int  $cf7_post_id  cf7 post id
   * @return Form_2_Post_Mapper  a factory oject
   */
  public static function get_post_mapper( $cf7_post_id ){
    $factory = self::get_factory();
    //if mapper exists, return it.
    if(isset($factory->post_mappers[$cf7_post_id])){
      return $factory->post_mappers[$cf7_post_id];
    }
    //check if the cf7 form already has a mapping
    $post_type = get_post_meta($cf7_post_id,'_cf7_2_post-type',true);
    $post_type_source = 'factory';
    $mapper = null;
    $form = get_post($cf7_post_id);
    //debug_msg('type='.$post_type);
    if(empty($post_type)){ //let's create a new one
      $post_type = $form->post_name;
      $plural_name = $singular_name = $form->post_title;
      $mapper = new Form_2_Custom_Post($cf7_post_id, $factory);
      if( 's'!= substr($plural_name,-1) ) $plural_name.='s';
      $mapper->init_default($post_type,$singular_name,$plural_name);
    }else{

      $post_type_source = get_post_meta($cf7_post_id,'_cf7_2_post-type_source',true);
      $map = get_post_meta($cf7_post_id,'_cf7_2_post-map',true);
      if( isset($factory->post_mappers[$cf7_post_id]) ){
        $mapper = $factory->post_mappers[$cf7_post_id];
      }else{
        switch($post_type_source){
          case 'system':
            $mapper = new Form_2_System_Post($cf7_post_id, $factory);
            break;
          case 'factory':
            $mapper = new Form_2_Custom_Post($cf7_post_id, $factory);
        }
        $mapper->load_post_mapping(); //load DB values
      }
     }
     return $mapper;
   }
   /**
   * Track mappers.
   *
   *@since 5.0.0
   *@param Form_2_Post_Mapper $mapper mapper object.
   */
   public function register($mapper){
     $this->post_mappers[$mapper->form_ID()] = $mapper;
   }
  /**
   *Enqueue the localised script
   *This function is called by the hook in the
   * @since 1.3.0
   * @param      string    $p1     .
   * @return     string    $p2     .
  **/
  public function enqueue_localised_script($handle, $field_and_values = array()){
    $values = array_diff($field_and_values, $this->localise_values);
    wp_localize_script($handle, 'cf7_2_post_local', $values);
  }

  /**
	 * Store the mapping in the CF7 post & create the custom post mapping.
	 * This is called by the plugin admin class function ajax_save_post_mapping whih is hooked to the ajax form call
	 * @since    5.0.0
   * @return  boolean   true if successful
   */
  public function save($post_id){
    $mapped = false;
    if( isset($_POST['mapped_post_type_source']) ) {
      $source = sanitize_text_field($_POST['mapped_post_type_source']);
      $mapper=null;
      switch($source){
        case 'system':
          $mapper = new Form_2_System_Post($post_id);
          break;
        case 'factory':
          $mapper = new Form_2_Custom_Post($post_id);
          break;
      }
      if(isset($mapper) && is_a($mapper, 'Form_2_Post_Mapper' )) $mapped = $mapper->save_mapping();
      else{
        debug_msg('CF&_2_POST ERROR: Unable to determine mapped_post_type_source while saving');
      }
    }else{
      debug_msg('CF&_2_POST ERROR: mapped_post_type_source missing, unable to save.');
    }
    //
    return $mapped;
  }


   /**
 	 * Get the CF7 post id.
 	 *
 	 * @since    1.0.0
   * @return int the cf7 form post ID
    */
   public function get_cf7_post_id(){
     return $this->cf7_post_ID;
   }

  /**
  * Register Custom Post Type based on CF7 mapped properties
  *
  * @since 1.0.0
  */
  protected function create_cf7_post_type() {
    //register any custom taxonomy
    if( !empty($this->post_properties['taxonomy']) ){
      foreach($this->post_properties['taxonomy'] as $taxonomy_slug){
        if('system' == $this->taxonomy_properties[$taxonomy_slug]['source']){
          continue;
        }
        $taxonomy = array(
      		'hierarchical'               => true,
      		'public'                     => true,
      		'show_ui'                    => true,
      		'show_admin_column'          => true,
      		'show_in_nav_menus'          => true,
      		'show_tagcloud'              => true,
          'show_in_quick_edit'         => true,
          'menu_name'                  => $this->taxonomy_properties[$taxonomy_slug]['name'],
          'description'                =>'',
      	);
        //debug_msg($this->taxonomy_properties[$taxonomy_slug]," taxonomy properties: ".$taxonomy_slug);
        $taxonomy =  array_merge( $this->taxonomy_properties[$taxonomy_slug], $taxonomy );
        $taxonomy_filtered = apply_filters('cf7_2_post_filter_taxonomy_registration-'.$taxonomy_slug, $taxonomy);
        //ensure we have all the key defined.
        $taxonomy =  $taxonomy_filtered + $taxonomy; //this will give precedence to filtered keys, but ensure we have all required keys.
        $this->register_custom_taxonomy($taxonomy);
      }
    }
  	$labels = array(
  		'name'                  => $this->post_properties['plural_name'],
  		'singular_name'         => $this->post_properties['singular_name'],
  		'menu_name'             => $this->post_properties['plural_name'],
  		'name_admin_bar'        => $this->post_properties['singular_name'],
  		'archives'              => $this->post_properties['singular_name'].' Archives',
  		'parent_item_colon'     => 'Parent '.$this->post_properties['singular_name'].':',
  		'all_items'             => 'All '.$this->post_properties['plural_name'],
  		'add_new_item'          => 'Add New '.$this->post_properties['singular_name'],
  		'add_new'               => 'Add New',
  		'new_item'              => 'New '.$this->post_properties['singular_name'],
  		'edit_item'             => 'Edit '.$this->post_properties['singular_name'],
  		'update_item'           => 'Update '.$this->post_properties['singular_name'],
  		'view_item'             => 'View '.$this->post_properties['singular_name'],
  		'search_items'          => 'Search '.$this->post_properties['singular_name'],
  		'not_found'             => 'Not found',
  		'not_found_in_trash'    => 'Not found in Trash',
  		'featured_image'        => 'Featured Image',
  		'set_featured_image'    => 'Set featured image',
  		'remove_featured_image' => 'Remove featured image',
  		'use_featured_image'    => 'Use as featured image',
  		'insert_into_item'      => 'Insert into '.$this->post_properties['singular_name'],
  		'uploaded_to_this_item' => 'Uploaded to this '.$this->post_properties['singular_name'],
  		'items_list'            => $this->post_properties['plural_name'].' list',
  		'items_list_navigation' => $this->post_properties['plural_name'].' list navigation',
  		'filter_items_list'     => 'Filter '.$this->post_properties['plural_name'].' list',
  	);
    //labels can be modified post taxonomy registratipn
    //ensure author is supported,
    if(!isset($this->post_properties['supports']['author'])) $this->post_properties['supports'][]='author';
  	$args = array(
  		'label'                 => $this->post_properties['singular_name'],
  		'description'           => 'Post for CF7 Form'. $this->post_properties['cf7_title'],
  		'labels'                => $labels,
      'supports'              => apply_filters('cf7_2_post_supports_'.$this->post_properties['type'], $this->post_properties['supports']),
  		'taxonomies'            => $this->post_properties['taxonomy'],
  		'hierarchical'          => !empty($this->post_properties['hierarchical']),
  		'public'                => !empty($this->post_properties['public']),
  		'show_ui'               => !empty($this->post_properties['show_ui']),
  		'show_in_menu'          => !empty($this->post_properties['show_in_menu']),
  		'menu_position'         => $this->post_properties['menu_position'],
  		'show_in_admin_bar'     => !empty($this->post_properties['show_in_admin_bar']),
  		'show_in_nav_menus'     => !empty($this->post_properties['show_in_nav_menus']),
  		'can_export'            => !empty($this->post_properties['can_export']),
  		'has_archive'           => !empty($this->post_properties['has_archive']),
  		'exclude_from_search'   => !empty($this->post_properties['exclude_from_search']),
  		'publicly_queryable'    => !empty($this->post_properties['publicly_queryable']),
  	);
    $reference = array(
        'edit_post' => '',
        'edit_posts' => '',
        'edit_others_posts' => '',
        'publish_posts' => '',
        'read_post' => '',
        'read_private_posts' => '',
        'delete_post' => ''
    );
    $capabilities = array_filter(apply_filters('cf7_2_post_capabilities_'.$this->post_properties['type'], $reference));
    $diff=array_diff_key($reference, $capabilities);
    if( empty( $diff ) ) {
      $args['capabilities'] = $capabilities;
      $args['map_meta_cap'] = true;
    }else{ //some keys are not set, so capabilities will not work
      //set to defaul post capabilities
      $args['capability_type'] = 'post';
    }

    //allow additional settings
    $args = apply_filters('cf7_2_post_register_post_'.$this->post_properties['type'], $args );

  	register_post_type( $this->post_properties['type'], $args );
    //link the taxonomy and the post
    foreach($this->post_properties['taxonomy'] as $taxonomy_slug){
      register_taxonomy_for_object_type( $taxonomy_slug, $this->post_properties['type'] );
    }
  }
  /**
  * Return the post_types to which forms are mapped
  *@since 3.4.0
  *@return array $cf7_post_id=>array($psot_type=>[factory|system|filter]) key value pairs
  */
  public static function get_mapped_post_types(){
    if(isset(self::$mapped_post_types)){
      return self::$mapped_post_types;
    }
    global $wpdb;
    $cf7_posts = $wpdb->get_results(
      "SELECT posts.ID, pmap.type, psource.origin FROM $wpdb->postmeta AS meta, $wpdb->posts AS posts,
      (SELECT post_id AS id, meta_value AS origin FROM $wpdb->postmeta WHERE meta_key='_cf7_2_post-type_source') AS psource,
      (SELECT post_id AS id, meta_value AS type FROM $wpdb->postmeta WHERE meta_key='_cf7_2_post-type') AS pmap WHERE posts.ID=post_id
      AND post_status LIKE 'publish'
      AND posts.ID=psource.id
      AND posts.ID = pmap.id"
    );
    self::$mapped_post_types = array();
    foreach($cf7_posts as $post){
      self::$mapped_post_types[$post->ID]=array($post->type=>$post->origin);
    }
    return self::$mapped_post_types;
  }
  /**
  * Function to check post types to which forms have been mapped.
  *
  *@since 3.4.0
  *@param string $post_type post type to check
  *@param string $source origin of post, default is 'factory', ie the origin is this class.
  *@return boolean true if mapped.
  */
  public static function is_mapped_post_types($post_type, $source=null){
    $is_mapped = false;
    if(isset(self::$mapped_post_types)){
      foreach(self::$mapped_post_types as $post_id=>$type){
        $ptype = key($type);
        if($post_type == $ptype){
          if(empty($source)){
            $is_mapped = $post_id;
          }else if( $source == $type[$ptype] ){
            $is_mapped = $post_id;
          }
        }
      }
    }
    return $is_mapped;
  }
  /**
  * Update the mapped post types when their status change.
  * @since 3.4.0.
  * @param $cf7_post_id form post id.
  * @param $status mapping status, publish|draft|delete, defaults to delete.
  */
  public static function update_mapped_post_types($cf7_post_id, $status='delete'){
    switch($status){
      case 'delete':
        unset( self::$mapped_post_types[$cf7_post_id] );
        break;
      case 'publish':
        update_post_meta($cf7_post_id, '_cf7_2_post-map', $status);
        $type = get_post_meta($cf7_post_id, '_cf7_2_post-type', true);
        $source = get_post_meta($cf7_post_id, '_cf7_2_post-type_source', true);
        self::$mapped_post_types[$cf7_post_id]=array($type, $source);
        break;
      case 'draft':
        update_post_meta($cf7_post_id, '_cf7_2_post-map', $status);
        unset( self::$mapped_post_types[$cf7_post_id] );
        break;
    }
  }
  /**
  * Dynamically registers new custom post.
  * Hooks 'init' action.
  * @since 1.0.0
  */
  public static function register_cf7_post_maps(){
    $cf7_post_ids = self::get_mapped_post_types();
    foreach($cf7_post_ids as $post_id=>$type){
      $system = true;
      $post_type = key($type);
      $cf7_2_post_map = self::get_factory($post_id);
      switch($type[$post_type]){
        case 'factory':
          $cf7_2_post_map->create_cf7_post_type();
          /**
          * Flush the permalink rules to ensure the public posts are visible on the front-end.
          * @since 3.8.2.
          */
          if($cf7_2_post_map->flush_permalink_rules){
            flush_rewrite_rules();
            update_post_meta($post_id,'_cf7_2_post_flush_rewrite_rules', false);
            $cf7_2_post_map->flush_permalink_rules = false;
          }
          $system = false;
          break;
        case 'system': /** @since 3.3.1 link system taxonomy*/
          //link the taxonomy and the post
          $taxonomies = get_post_meta($post_id, '_cf7_2_post-taxonomy', true);
          foreach($taxonomies as $taxonomy_slug){
            register_taxonomy_for_object_type( $taxonomy_slug, $post_type );
          }
          break;
      }
      /**
      * action to notify other plugins for mapped post creation
      * @since 2.0.4
      * @param string $post_type   the post type being mapped to
      * @param boolean $system   true if form is mapped to an existing post, false if it is being registered by this plugin.
      * @param string $cf7_key   the form key value which is being mapped to the post type
      * @param string $post_id   the form post ID value which is being mapped to the post type
      */
      do_action('cf72post_register_mapped_post', $post_type, $system, $cf7_2_post_map->cf7_key, $post_id);
      //add a filter for newly saved posts of this type.
      add_action('save_post_'.$post_type, function($post_id, $post, $update){
        if($update) return $post_id;
        $cf7_flag = get_post_meta($post_id, '_cf7_2_post_form_submitted', true);
        if(empty($cf7_flag)){ /** @since 4.1.9 default to yes */
          update_post_meta($post_id, '_cf7_2_post_form_submitted', 'yes');
        }
        return $post_id;
      }, 10,3);
    }
  }

   /**
   * Checks if a form mapping is published
   * @since 2.0.0
   */
   public static function is_mapped($cf7_post_ID){
     $map = get_post_meta($cf7_post_ID, '_cf7_2_post-map', true);

     if($map && 'publish'== $map){
       return true;
     }else{
       return false;
     }
   }
  /**
  * Builds a set of field=>value pairs to pre-populate a mapped form
  * Called by Cf7_2_Post_Public::load_cf7_script()
  * @since 1.3.0
  * @param   Int  $cf7_2_post_id   a specific post to which this form submission is mapped/saved
  * @return    Array  cf7 form field=>value pairs.
  */
  public static function get_form_values($form_id, $cf7_2_post_id=''){
    //is user logged in?
    $load_saved_values = false;
    $post=null;
    $mapper = self::get_post_mapper($form_id);
    $field_and_values = array();
    $unmapped_fields = array();
    $mapper->load_form_fields(); //this loads the cf7 form fields and their type


    if(is_user_logged_in()){ //let's see if this form is already mapped for this user
      $user = wp_get_current_user();
      //find out if this user has a post already created/saved
      $args = array(
      	'posts_per_page'   => 1,
      	'post_type'        => $this->post_properties['type'],
      	'author'	   => $user->ID,
      	'post_status'      => 'any'
      );
      if(!empty($cf7_2_post_id)){ //search for the sepcific mapped/saved post
        $args['post__in']=array($cf7_2_post_id);
      }
      //filter by submission value for newer version so as not to break older version
      if( version_compare( CF7_2_POST_VERSION , $mapper->post_properties['version'] , '>=') ){
        $args['meta_query'] = array(
    		array(
    			'key'     => '_cf7_2_post_form_submitted',
    			'value'   => 'no',
    			'compare' => 'LIKE',
    		));
      }


      $args = apply_filters('cf7_2_post_filter_user_draft_form_query', $args, $mapper->post_properties['type'], $mapper->cf7_key);
      $posts_array = get_posts( $args );
      //debug_msg($args, "looking for posts.... found, ".sizeof($posts_array));
      if(!empty($posts_array)){
        $post = $posts_array[0];
        $load_saved_values = true;
        $field_and_values['map_post_id']= $post->ID;
        wp_reset_postdata();
      }

    }
      //we now need to load the save meta field values
      foreach($mapper->post_map_fields as $form_field => $post_field){
        $post_key ='';
        $post_value = '';
        $skip_loop = false;
        //if the value was filtered, let's skip it
        if( 0 === strpos($form_field,'c2p_filter-') || 0 === strpos($form_field,'cf7_2_post_filter-') ){
          continue;
        }

        switch($post_field){
          case 'title':
          case 'author':
          case 'excerpt':
            $post_key = 'post_'.$post_field;
            break;
          case 'editor':
            $post_key ='post_content';
            break;
          case 'slug':
            $post_key ='post_name';
            break;
          case 'thumbnail':
            break;
        }
        if($load_saved_values) {
          $post_value = $post->{$post_key};
        }else{
          $post_value = apply_filters('cf7_2_post_filter_cf7_field_value', $post_value, $mapper->cf7_post_ID, $form_field, $mapper->cf7_key, $mapper->form_terms);
        }

        if(!empty($post_value)){
          $field_and_values[str_replace('-','_',$form_field)] = $post_value;
        }
      }
      //
      //----------- meta fields
      //
      //debug_msg($this->post_map_meta_fields, "loading meta fields mappings...");
      foreach($mapper->post_map_meta_fields as $form_field => $post_field){
        $post_value='';
        //if the value was filtered, let's skip it
        if( 0 === strpos($form_field,'c2p_filter-') || 0 === strpos($form_field,'cf7_2_post_filter-') ) {
          continue;
        }
        //get the meta value
        if($load_saved_values) {
          $post_value = get_post_meta($post->ID, $post_field, true);
        }else{
          //debug_msg('spllygin filter cf7_2_post_filter_cf7_field_value'. $form_field);
          $post_value = apply_filters('cf7_2_post_filter_cf7_field_value', $post_value, $mapper->cf7_post_ID, $form_field, $mapper->cf7_key, $mapper->form_terms);
        }
        if(!empty($post_value)){
          $field_and_values[str_replace('-','_',$form_field)] = $post_value;
        }
      }
      /*
       Finally let's also allow a user to load values for unammaped fields
      */
      $unmapped_fields = array_diff_key( $mapper->cf7_form_fields, $mapper->post_map_meta_fields, $mapper->post_map_fields, $mapper->post_map_taxonomy );
      foreach($unmapped_fields as $form_field=>$type){
        if('submit' == $type){
          continue;
        }
        $post_value='';
        $post_value = apply_filters('cf7_2_post_filter_cf7_field_value', $post_value, $mapper->cf7_post_ID, $form_field, $mapper->cf7_key, $mapper->form_terms);
        //$script .= $this->get_field_script($form_field, $post_value);
        if(!empty($post_value)){
          $field_and_values[str_replace('-','_',$form_field)] = $post_value;
        }
      }
      //
      // ------------ taxonomy fields
      //
      $load_chosen_script=false;
      foreach($mapper->post_map_taxonomy as $form_field => $taxonomy){
        //if the value was filtered, let's skip it
        if( 0 === strpos($form_field,'c2p_filter-') || 0 === strpos($form_field,'cf7_2_post_filter-') ){
          continue;
        }
        $terms_id = array();
        if( $load_saved_values ) {
          $terms = get_the_terms($post, $taxonomy);
          if(empty($terms)) $terms = array();
          foreach($terms as $term){
            $terms_id[] = $term->term_id;
          }
        }else{
          $terms_id = apply_filters('cf7_2_post_filter_cf7_taxonomy_terms',$terms_id, $mapper->cf7_post_ID, $form_field, $mapper->cf7_key);
          if( is_string($terms_id) ){
            $terms_id = array($terms_id);
          }
        }
        //load the list of terms
        //debug_msg("buidling options for taxonomy ".$taxonomy);
        $field_type = $mapper->cf7_form_fields[$form_field];
        $options = $mapper->get_taxonomy_terms($taxonomy, 0, $terms_id, $form_field, $field_type);
        //for legacy purpose
        $apply_jquery_select = apply_filters('cf7_2_post_filter_cf7_taxonomy_chosen_select',true, $mapper->cf7_post_ID, $form_field, $mapper->cf7_key) && apply_filters('cf7_2_post_filter_cf7_taxonomy_select2',true, $mapper->cf7_post_ID, $form_field, $mapper->cf7_key);
        if( $apply_jquery_select ){
          wp_enqueue_script('jquery-select2',plugin_dir_url( dirname( __FILE__ ) ) . 'assets/select2/js/select2.min.js', array('jquery'),CF7_2_POST_VERSION,true);
          wp_enqueue_style('jquery-select2',plugin_dir_url( dirname( __FILE__ ) ) . 'assets/select2/css/select2.min.css', array(),CF7_2_POST_VERSION);
        }
        $field_and_values[str_replace('-','_',$form_field)] = wp_json_encode($options);

      }
    //filter the values
    $field_and_values = apply_filters('cf7_2_post_form_values', $field_and_values, $mapper->cf7_post_ID , $mapper->post_properties['type'], $mapper->cf7_key, $post);
    //make sure the field names are with underscores
    $return_values = array();
    foreach($field_and_values as $field=>$value){
      $return_values[str_replace('-','_',$field)]=$value;
    }
    return $return_values;
  }

  /**
  * Function to print jquery script for form field initialisation
  *
  * @since 1.3.0
  * @param   Array  $field_and_values   array of $field_name=>$values pairs
  * @param   Int  $cf7_2_post_id   a specific post to which this form submission is mapped/saved
  */
  public function get_form_field_script($nonce){
    ob_start();
    $factory = $this;
    include( plugin_dir_path( __FILE__ ) . '/partials/cf7-2-post-script.php');
    $script = ob_get_contents ();
    ob_end_clean();
    return $script;
  }
  /**
   * Function to return taxonomy terms as either options list for dropdown, checkbox, or radio
   * This is used for system post mapping in conjunstion with the filter 'cf7_2_post_load-{$post_type}'
   * @since 1.3.2
   * @param   String    $taxonomy     The taxonomy slug from which to retrieve the terms.
   * @param   String    $parent     the parent branch for which to retrieve the terms (by default 0).
   * @param   Array     $post_term_ids an array of term ids which a post has been tagged with
   * @param   String    $field form field name for which this taxonomy is mapped to.
   * @param   String    $field_type the type of field in which the tersm are going to be listed
   * @return  String    json encoded HTML script to be used as value for the $field     .
  **/
  // public function get_taxonomy_mapping($taxonomy, $parent=0, $post_term_ids, $field){
  //   $this->load_form_fields();
  //   $script = $this->get_taxonomy_terms( $taxonomy, $parent, $post_term_ids, $field, $this->cf7_form_fields[$field] );
  //   return json_encode($script);
  // }
  /**
  * Function to retrieve jquery script for form field taxonomy capture
  *
  * @since 1.2.0
  * @param   String $taxonomy  the taxonomy slug for which to return the list of terms
  * @param   Int  $parent  the parent ID of child terms to fetch
  * @param   Array  $post_terms an array of terms which a post has been tagged with
  * @param   String   $field form field name for which this taxonomy is mapped to.
  * @param   String $field_type the type of field in which the tersm are going to be listed
  * @param   int $level a 0-based integer to denote the child-nesting level of the hierarchy terms being collected.
  * @return  String a jquery code to be executed once the page is loaded.
  */
   protected function get_taxonomy_terms( $taxonomy, $parent, $post_terms, $field, $field_type, $level=0){
    $args = array(
      'parent' => $parent,
      'hide_empty' => 0,
    );
    $args = apply_filters('cf7_2_post_filter_taxonomy_query', $args, $mapper->cf7_post_ID, $taxonomy, $field, $mapper->cf7_key);
    /**
    * allows for more felxibility in filtering taxonomy options.
    *@since 3.5.0
    */
    if(empty($args)){
      return '';
    }
    //check the WP version
    global $wp_version;
	  if ( $wp_version >= 4.5 ) {
      $args['taxonomy'] = $taxonomy;
	    $terms = get_terms($args); //WP>= 4.5 the get_terms does not take a taxonomy slug field
    }else{
      $terms = get_terms($taxonomy, $args);
    }
    if( is_wp_error( $terms ) ){
      debug_msg('Taxonomy '.$taxonomy.' does not exist');
      return '';
    }else if( empty($terms) ){
      //debug_msg("No Terms found for taxonomy: ".$taxonomy.", parent ".$parent);
      return'';
    }
    //build the list
    $term_class = 'cf72post-'.$taxonomy;
    $nl = '';//PHP_EOL;
    $script = '<fieldset class="top-level '.$term_class.'">';
    if($parent > 0){
      $script = '<fieldset class="cf72post-child-terms parent-term-'.$parent.'">';
      $term_class .= ' cf72post-child-term';
    }
    //if we are dealing with a dropdown, then don't group fieldsets
    if('select' == $field_type) $script = '';
    //loop over all terms
    foreach($terms as $term){
      $term_id = $term->term_id;
      $is_optgroup=false;
      $custom_classes = array();
      $custom_attributes = array();
      $custom_class = $term_class;
      /**
      * filter classes for terms to allow addition of custom classes.
      * @param Array $custom_classes an array of strings.
      * @param WP_Term $term current term object being setup.
      * @param int $level a 0-based integer to denote the child-nesting level of the hierarchy terms being.
      * @param $field string form field being mapped.
      * @param $formKey string unique key of form being mapped.
      * @return Array an array of strings.
      * @since 3.8.0
      */
      $custom_classes = apply_filters('cf72post_filter_taxonomy_term_class', $custom_classes, $term, $level, $field, $mapper->cf7_key);

      if($custom_classes && is_array($custom_classes)){
        $custom_class .= ' '.implode(' ', $custom_classes);
      }
      /**
      * filter attributes for terms <input/> or <option> elemets to allow addition of custom attributes.
      * @param Array $custom_attributes an array of $attribute=>$value pairs.
      * @param WP_Term $term current term object being setup.
      * @param int $level a 0-based integer to denote the child-nesting level of the hierarchy terms being.
      * @param $field string form field being mapped.
      * @param $formKey string unique key of form being mapped.
      * @return Array an array of $attribute=>$value pairs.
      * @since 3.8.0
      */
      $custom_attributes = apply_filters('cf72post_filter_taxonomy_term_attributes',$custom_attributes, $term, $level, $field, $mapper->cf7_key);
      $attributes = '';
      if($custom_attributes && is_array($custom_attributes)){
        foreach($custom_attributes as $attr=>$value){
          $attributes .= ' '.$attr.'="'.(string)$value.'"';
        }
      }
      switch($field_type){
        case 'select':
          //debug_msg("Checking option: ".$mapper->cf7_post_ID." field(".$field."), term ".$term->name);
          //check if we group these terms
          if(0==$parent){
            //do we group top level temrs as <optgroup/> ?
            $groupOptions = false;
            $children = get_term_children($term_id, $taxonomy);
            if($children) $groupOptions = true;
            //let's filter this choice
            $groupOptions = apply_filters('cf7_2_post_filter_cf7_taxonomy_select_optgroup',$groupOptions, $mapper->cf7_post_ID, $field, $term, $mapper->cf7_key);

             if($groupOptions){
              $script .='<optgroup label="'.$term->name.'">';
              $is_optgroup=true;
            }
          }
          if(!$is_optgroup){
            if( in_array($term_id, $post_terms) ){
              $script .='<option'.$attributes.' class="'.$custom_class.'" value="'.$term_id.'" selected="selected">'.$term->name.'</option>';
            }else{
              $script .='<option'.$attributes.' class="'.$custom_class.'" value="'.$term_id.'" >'.$term->name.'</option>';
            }
          }
          break;
        case 'radio':
          $check = '';
          if( in_array($term_id, $post_terms) ){
            $check = 'checked';
          }
          $script .='<div id="'.$term->slug.'" class="radio-term"><input'.$attributes.' type="radio" name="'.$field.'" value="'.$term_id.'" class="'.$custom_class.'" '.$check.'/>';
          $script .='<label>'.$term->name.'</label></div>'.$nl;
          break;
        case 'checkbox':
          $check = '';
          if( in_array($term_id, $post_terms) ){
            $check = 'checked';
          }
          $field_name = $field;
          if( !$mapper->field_has_option($field, 'exclusive') ){
            $field_name = $field.'[]';
          }
          $script .='<div id="'.$term->slug.'" class="checkbox-term"><input'.$attributes.' type="checkbox" name="'.$field_name.'" value="'.$term_id.'" class="'.$custom_class.'" '.$check.'/>';
          $script .='<label>'.$term->name.'</label></div>'.$nl;
          break;
        default:
          return ''; //nothing more to do here
          break;
      }
      //get children
      $parent_level = $level;
      $script .= $mapper->get_taxonomy_terms($taxonomy, $term_id, $post_terms, $field, $field_type, $level+1);
      if($is_optgroup) $script .='</optgroup>';
    }
    if('select' != $field_type) $script .='</fieldset>';

    return $script;
  }
  /**
  * regsiter a custom taxonomy
  * @since 2.0.0
  * @param  Array  $taxonomy  a, array of taxonomy arguments
  */
  protected function register_custom_taxonomy($taxonomy) {
  	$labels = array(
  		'name'                       =>  $taxonomy["name"],
  		'singular_name'              =>  $taxonomy["singular_name"],
  		'menu_name'                  =>  $taxonomy["menu_name"],
  		'all_items'                  =>  'All '.$taxonomy["name"],
  		'parent_item'                =>  'Parent '.$taxonomy["singular_name"],
  		'parent_item_colon'          =>  'Parent '.$taxonomy["singular_name"].':',
  		'new_item_name'              =>  'New '.$taxonomy["singular_name"].' Name',
  		'add_new_item'               =>  'Add New '.$taxonomy["singular_name"],
  		'edit_item'                  =>  'Edit '.$taxonomy["singular_name"],
  		'update_item'                =>  'Update '.$taxonomy["singular_name"],
  		'view_item'                  =>  'View '.$taxonomy["singular_name"],
  		'separate_items_with_commas' =>  'Separate '.$taxonomy["name"].' with commas',
  		'add_or_remove_items'        =>  'Add or remove '.$taxonomy["name"],
  		'choose_from_most_used'      =>  'Choose from the most used',
  		'popular_items'              =>  'Popular '.$taxonomy["name"],
  		'search_items'               =>  'Search '.$taxonomy["name"],
  		'not_found'                  =>  'Not Found',
  		'no_terms'                   =>  'No '.$taxonomy["name"],
  		'items_list'                 =>  $taxonomy["name"].' list',
  		'items_list_navigation'      =>  $taxonomy["name"].' list navigation',
  	);
    //labels can be modified post registration
  	$args = array(
  		'labels'                     => $labels,
  		'hierarchical'               => $taxonomy["hierarchical"],
  		'public'                     => $taxonomy["public"],
  		'show_ui'                    => $taxonomy["show_ui"],
  		'show_admin_column'          => $taxonomy["show_admin_column"],
  		'show_in_nav_menus'          => $taxonomy["show_in_nav_menus"],
  		'show_tagcloud'              => $taxonomy["show_tagcloud"],
      'show_in_quick_edit'         => $taxonomy["show_in_quick_edit"],
      'description'                => $taxonomy["description"],
  	);
    if(isset($taxonomy['meta_box_cb'])){
      $args['meta_box_cb'] = $taxonomy['meta_box_cb'];
    }
    if(isset($taxonomy['update_count_callback'])){
      $args['update_count_callback'] = $taxonomy['update_count_callback'];
    }
    if(isset($taxonomy['capabilities'])){
      $args['capabilities'] = $taxonomy['capabilities'];
    }
    $post_types = apply_filters('cf7_2_post_filter_taxonomy_register_post_type', array( $mapper->post_properties["type"] ), $taxonomy["slug"]);
  	register_taxonomy( $taxonomy["slug"], $post_types, $args );

  }
  
  /**
  *  Retrieves select dropdpwn fields populated with existing emta fields
  * for each system post visible in the form mapping admin page.
  *
  *@since 5.0.0
  *@param string $param text_description
  *@return string text_description
  */
  public static function get_all_metafield_menus(){
    $factory = self::get_factory();
    $html = '<div class="system-posts-metafields display-none">'.PHP_EOL;
    foreach($factory->system_post_types as $post_type=>$label){
      $html .= $factory->get_metafield_menu($post_type,'');
    }
    $html .= '</div>'.PHP_EOL;
    return $html;
  }

  /**
   * Get a list of meta fields for the requested post_type
   * @since 5.0.0
   * @param      String    $post_type     post_type for which meta fields are requested.
   * @return     String    a list of option elements for each existing meta field in the DB.
  **/
  protected function get_metafield_menu($post_type, $selected_field){
    global $wpdb;
    $metas = $wpdb->get_results($wpdb->prepare(
      "SELECT DISTINCT meta_key
      FROM {$wpdb->postmeta} as wpm, {$wpdb->posts} as wp
      WHERE wpm.post_id = wp.ID AND wp.post_type = %s",
      $post_type
    ));
    $has_fields = false;
    $disabled=$html='';
    if(empty($selected_field)){
      $disabled=' disabled="true"';
    }
    if(false !== $metas){
      $html = '<div id="c2p-'.$post_type.'" class="system-post-metafield">'.PHP_EOL;
      $select = '<select'.$disabled.' class="existing-fields">'.PHP_EOL;
      $select .= '<option value="">'.__('Select a field','post-my-contact-form-7').'</option>'.PHP_EOL;
      foreach($metas as $row){
        if( 0=== strpos( $row->meta_key, '_') &&
        /**
        * filter plugin specific (internal) meta fields starting with '_'. By defaults these are skiupped by this plugin.
        * @since 2.0.0
        * @param boolean $skip true by default
        * @param string $post_type post type under consideration
        * @param string $meta_key meta field name
        */
        apply_filters('cf7_2_post_skip_system_metakey',true, $post_type, $row->meta_key) ){
          //skip _meta_keys, assuming system fields.
          continue;
        }//end if
        $selected = ($selected_field == $row->meta_key) ? ' selected="true"':'';
        $select .= '<option value="'.$row->meta_key.'"'.$selected.'>'.$row->meta_key.'</option>'.PHP_EOL;
        $has_fields = true;
      }
      if($has_fields){
        $select .= '<option value="cf72post-custom-meta-field">'.__('Custom field','post-my-contact-form-7').'</option>'.PHP_EOL;
        $select .='</select>'.PHP_EOL;
        $html .= $select;
        $html .= '<input'.$disabled.' class="cf7-2-post-map-label-custom display-none" type="text" value="custom_meta_key" disabled />'.PHP_EOL;
        $html .= '</div>';
      }else $html='';
    }
    return $html;
  }
}
