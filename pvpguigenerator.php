<?php 
/*
Plugin Name: PathVisio Plugin Gui Generator
Plugin URI: http://www.pathvisio.org/
Description: Generates a user interface in WP for the Pathvisio plugin repository
Version: 0.9
Author: Nuno Nunes
Author URI:
License: GPL2.0
*/
?>
<?



function pv_create_category(){
	$category = get_category_by_path('plugins');

	if($category == null) wp_create_category("plugins");	
}

add_action( 'init', 'create_post_type' );


function sbt_custom_excerpt_more( $output ) {return preg_replace('/<a[^>]+>Continue reading.*?<\/a>/i','',$output);
}
add_filter( 'get_the_excerpt', 'sbt_custom_excerpt_more', 20 );



function create_post_type() {
        register_post_type( 'plugin',
                array(
                        'labels' => array(
                                'name' => __( 'PathVisio Plugins' ),
                                'singular_name' => __( 'PathVisio Plugin' )
                        ),
		'post_name' => 'xpoto',
                'capability_type' => 'post',
                'public' => true,
                'has_archive' => true,
                'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'post-formats' ), 
		'taxonomies' => array('post_tag'),
		'hierarchical' => true,
                )
        );
}



function pv_create_content(){
	global $wpdb;	

	$bundleName = '';
	$bundleDescription = '';

	//get everything from DB
	$aBundle = $wpdb->get_results("SELECT * FROM bundle WHERE type='plugin'");

	$thisCat = get_category_by_path('/plugins');
	
	print_r($thisCat);	

	foreach($aBundle as $bundle){
		$bundleName = $bundle->name;
		$content = $bundle->description;
		$bundleId = $bundle->bundle_id;
		$bundleShortDescription = $bundle->short_description;
		$bundleLatestVersion = "unknown";
		$jar_file_url = "";
		$release_notes = "";
		$faq = $bundle->faq;
		$pv_required = '';
		$pv_tested = '';		
		$aBundleId = Array();
		
		$aBundleVersion = $wpdb->get_results("SELECT * FROM bundle_version WHERE bundle_id = " . $bundle->bundle_id . " ORDER BY bundle_version_id ASC");
		

		/*Maybe create a function for this*/
		foreach($aBundleVersion as $bundleVersion){
			$bundleLatestVersion = $bundleVersion->version;
			$jar_file_url = $bundleVersion->jar_file_url;
			$release_notes = "<p><strong>Version ".$bundleVersion->version."</strong></p>" . $bundleVersion->release_notes;
			$pv_required = $bundleVersion->pv_required;
			$pv_tested = $bundleVersion->pv_tested;
			$bundleLastVersionId = $bundleVersion->bundle_version_id;
			$aBundleId[] = $bundleVersion->bundle_version_id;
			$sIcon = $bundleVersion->icon_url;
		}	

		$aAuthors = pv_get_bundle_authors_affiliation($aBundleId);
		$aBundleInformation = pv_get_bundle_information($bundleLastVersionId);

		$content .= "<p>License: <a target='_blank' href='".$aBundleInformation['license']."'>".$aBundleInformation["license"]."</a><br/>Website: <a target='_blank' href='".$aBundleInformation["website"]."'>".$aBundleInformation["website"]."</a></p>";

		$sAuthors = "";
		$last_developerId = null;
		foreach($aAuthors as $author){
			$authorname = $author->developer_id == $last_developerId ? "&nbsp;" : $author->firstname . " " . $author->lastname;
			$last_developerId = $author->developer_id;
			$sAuthors .= "<div><div style='width:180px; float:left;'> ".$authorname. "</div> <a href='".$author->website."' target='_blank'>".$author->name."</a></div>"  ;
		}



		if(strlen($pv_tested)=="") $pv_tested = $pv_required;

		$aPVTag = pv_get_plugin_tags($bundleId);
		//var_dump($aPVTag);

		$post_id = pv_get_post_id($bundleId);

		$post_exists = $post_id>0?true:false;		

		$my_post = array(
			  'post_title'    => $bundleName,
			  'post_content'  => $content,
			  'post_excerpt'  => $bundleShortDescription,			  
			  'post_status'   => 'publish',
			  'post_author'   => 1,
			  'post_type'	  => 'plugin', 
			  /*'post_category' => array($thisCat->id),*/
			  'tags_input'    => $aPVTag,
		);

		if($post_exists)
			$my_post['ID'] = $post_id;
		
		
		
		// Insert the post into the database
		if($post_exists)
			$post_id = wp_update_post( $my_post );
		else
			$post_id = wp_insert_post( $my_post );

		update_post_meta($post_id, 'bundle_version', "$bundleLatestVersion");
		update_post_meta($post_id, 'jar_file_url', $jar_file_url);
		update_post_meta($post_id, 'faq', $faq);
		update_post_meta($post_id, 'release_notes', $release_notes);
		update_post_meta($post_id, 'pv_required', $pv_required);
		update_post_meta($post_id, 'pv_tested', $pv_tested);
		update_post_meta($post_id, 'pv_affiliation', $bundleLatestAffiliation);
		update_post_meta($post_id, 'pv_affiliation_url', $bundleLatestAffiliationURL);
		update_post_meta($post_id, 'pv_website', $aBundleInformation["website"]);
		update_post_meta($post_id, 'pv_license', $aBundleInformation["license"]);
		update_post_meta($post_id, 'pv_authoraffiliation_list', $sAuthors);
		update_post_meta($post_id, 'pv_icon', $sIcon);
		
		if(!$post_exists)
			add_post_meta($post_id, 'bundle_id', "$bundleId");

	}

}

function pv_get_bundle_information($id){
	global $wpdb;
	
	$bundle_version = Array();

	$sql = "SELECT version, website, license FROM bundle, bundle_version WHERE bundle_version.bundle_id = bundle.bundle_id AND bundle_version_id = $id";

	$rs = $wpdb->get_results($sql);
	
	//print_r($sql);
	//var_dump($rs);

	if(isset($rs[0])){
		$bundle_version['version'] = $rs[0]->version;
		$bundle_version['website'] = $rs[0]->website;
		$bundle_version['license'] = $rs[0]->license;
	}

	return $bundle_version;

}


/*
$version_id: if an integer returns an array of authors for the bundleid. 
If it is an array it returns the authors for the given bundles
the list shows "distinct" set of <author,affiliation> (no repetitions)
*/
function pv_get_bundle_authors_affiliation($version_id){
	global $wpdb;

	$sFilter = "";

	if(is_int($author_id)){
		$sFilter = "bundle_version_id = $version_id";		
	} else if(is_array($version_id)){
		$sFilter = implode(" OR bundle_version_id = ",$version_id);
	}

	$sFilter = " ( bundle_version_id = " . $sFilter . " ) ";
	
	$sql  = "SELECT dev.firstname, dev.lastname, dev.email, aff.name, aff.website, av.developer_id FROM ";
	$sql .= "bundle_version_author AS av, developer AS dev, affiliation AS aff WHERE ";
	$sql .= "av.developer_id = dev.developer_id AND av.affiliation_id = aff.affiliation_id  AND ";
	$sql .= $sFilter;
	$sql .= " ORDER BY developer_id ASC";
//	var_dump($version_id);	
//	echo $sql . "<br/>";
		
	$a = $wpdb->get_results($sql);
//	echo "<pre>";
//	var_dump($a);
//	echo "</pre>";

	return $a;
}



add_action('wp_head', 'pv_create_content');


function pv_get_plugin_tags($bundle_id){
	global $wpdb;	
	
//	echo "SELECT * FROM bundle_categories, category WHERE bundle_categories.category_id = category.category_id AND bundle.categories.bundle_id  = $bundle_id";

	$aTag = $wpdb->get_results("SELECT * FROM bundle_categories, category WHERE bundle_categories.category_id = category.category_id AND bundle_categories.bundle_id = $bundle_id");

//	var_dump($aTag);

	$aSTag = Array();	
		//echo "GM $bundle_id";
	foreach($aTag as $tag){
		$aSTag[] = $tag->name;
	}

	//var_dump($aSTag);
	return $aSTag;
}

function pv_get_post_id($bundle_id){
  $query = new WP_Query( array(
     'post_type'   => 'plugin'
    ,'post_status' => array(
         'publish'
        ,'future'
        ,'pending'
     )
    ,'meta_query'  => array( array(
         'key'       => 'bundle_id'
        ,'value'     =>  $bundle_id
        /*,'compare'   => 'EXISTS'
        ,'type'      => 'NUMERIC'*/
     ) )
  ) );

  if($query->found_posts){
	$id = 0;

	$query->the_post();
	$id = get_the_ID();
	wp_reset_postdata();
//	echo 'a--'.$id;
	return $id;
  }else
	return 0;


}




function pv_widgets_init() {
	register_sidebar( array(
		'name' => 'Pathvisio Page Column',
		'id' => 'pv-options',
		'before_widget' => '<div>',
		'after_widget' => '</div>',
//		'before_title' => '<h2 class="rounded">',
//		'after_title' => '</h2>',
	) );
}

add_action('init', "pv_widgets_init");


function pv_most_viewed_plugins(){ 
    ob_start();
    //$query = new WP_Query('meta_key=post_views_count&orderby=meta_value_num&posts_per_page=5');
    $query = new WP_Query('posts_per_page=20&post_type=plugin&orderby=title&order=asc&post_status=publish');
    $iPluginsNr = $query->found_posts;
        for($n=0; $n < $iPluginsNr; $n=$n+2) {
	//while($query->have_posts()){ 
            //Iterate the post index in The Loop.
            $query->have_posts();
	    $query->the_post();
            ?>
	    <div class="row">
		<div class="span6">
		    <h4>
        	    <a href="<?php the_permalink() ?>" title="Permanent Link to: <?php the_title_attribute(); ?>">
			<img src="<?php echo get_post_meta(get_the_ID(),"pv_icon", true); ?>"/>
			<?php the_title(); ?><?php //echo get_post_meta(get_the_ID(),"bundle_id",true)  ?></a>
        	    </h4> 		    <?php the_excerpt(); ?>

		</div>
	   
		<div class="span6">
  	   	    <?php if($query->have_posts()){ 
				$query->the_post();?>
		    <h4>
        	    <a href="<?php the_permalink() ?>" title="Permanent Link to: <?php the_title_attribute(); ?>">
			<img src="<?php echo get_post_meta(get_the_ID(),"pv_icon", true); ?>"/>
			<?php the_title(); ?><?php //echo get_post_meta(get_the_ID(),"bundle_id",true)  ?></a>
        	    </h4>
		    <?php the_excerpt(); ?>

  	      	     <?php } ?>
		</div>		    
             </div>
	  
	     <?php
             }
     
    //Destroy the previous query. This is a MUST.
    wp_reset_query();
    $html = ob_get_contents();
    ob_end_clean();
    return $html;
}


function pv_options_sc( $atts ) {
    ob_start();
    dynamic_sidebar('pv-options');
    $html = ob_get_contents();
    ob_end_clean();
    return $html;
}

function pv_register_style(){
	wp_register_style(
	        'pv_plugins',
        	get_template_directory_uri() . "/style.css"
	);
}

function post_type_tags_fix($request) {
    if ( isset($request['tag']) && !isset($request['post_type']) )
    $request['post_type'] = 'plugin';
    return $request;
} 
add_filter('request', 'post_type_tags_fix');


//add_action( 'wp_enqueue_scripts', 'pv_register_style' );
add_shortcode( 'pv-options', 'pv_options_sc' );
add_shortcode( 'pvpopularplugins', 'pv_most_viewed_plugins' );


?>
