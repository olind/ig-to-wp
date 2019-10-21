<?php

function my_theme_enqueue_styles() {

    $parent_style = 'twentynineteen-style';

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style ),
        filemtime(get_template_directory() . '/style.css')
    );
}
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );

add_filter( 'after_setup_theme', 'parsejson' );

/*
    Put the exported json+image+photo files in wp-content/ig_download
*/
function parsejson(){

    $ig_json_file_uri = get_stylesheet_directory() . "/../../ig_download/media.json";
    $slices = json_decode( file_get_contents( $ig_json_file_uri ), true );
    
      //convert_photos_to_wp_posts($slices['photos'][16]);
      convert_videos_to_wp_posts($slices['videos'][2]);
        
      if ($slices['photos']) {
        foreach ($slices['photos'] as $slice) {
            //convert_photos_to_wp_posts($slice);
       }
    }

    if ($slices['videos']) { 
        foreach ($slices['videos'] as $slice) {
           //convert_videos_to_wp_posts($slice);
       }
    }
}

function convert_photos_to_wp_posts($insta_photos){
    convert_insta_posts_to_wp_posts($insta_photos, true);
}
function convert_videos_to_wp_posts($insta_videos){
    convert_insta_posts_to_wp_posts($insta_videos, false);
}

function convert_insta_posts_to_wp_posts( $photos, $is_photo ){

    $wp_photo_category_id = 1;
    $wp_video_category_id = 5;

    $post_id = -1;
    $author_id = 1;
    $taken_at = $photos['taken_at'];
    $yyyymm = $photos['taken_at'][0].$photos['taken_at'][1].$photos['taken_at'][2].$photos['taken_at'][3].'/'.$photos['taken_at'][5].$photos['taken_at'][6];
    $title = $photos['caption'];
    
    $location = $photos['location'];
    $local_file_name = explode("/",$photos['path'])[2];
    $local_directory = get_stylesheet_directory() . "/../../ig_download/";
    $ig_temp_directory = get_stylesheet_directory() . "/../../ig_download/temp";
    $local_file_path = $local_directory . $photos['path'] . '';
    $post_content = '<section class="insta-caption">' . $photos['caption'] . '</section><section class="insta-location">' . $location . '</section><section class="insta-imported">This post was automatically imported from Instagram 2019-10-10.</section>';

    if(strlen ($title) > 30) {
        $title = substr($title, 0, 27) . '...';
    }
    if(strlen ($title) < 1) {
        $title = $yyyymm.' - ' . $local_file_name;
    }

    $post_category = array();
    if($is_photo){
        $post_category[] = $wp_photo_category_id;
    } else {
        $post_category[] = $wp_video_category_id;
    }

//Create post without adding the image to get a post id where we can attach the image after it's uploaded
    $wp_post = array(
        'comment_status'	=>	'closed',
        'ping_status'		=>	'closed',
        'post_date'         =>  $taken_at,
        'post_author'		=>	$author_id,
        'post_title'		=>	$title,
        'post_content'      =>  $post_content,
        'post_status'		=>	'publish',
        'post_type'		    =>	'post',
        'post_category'     =>  $post_category
    );

    if( null == get_page_by_title( $title, OBJECT, 'post' ) ) {
        $post_id = wp_insert_post($wp_post);
    } else {
        echo "<pre>Inserting post failed<br>";
        echo "photos:<br>";
        var_dump($photos);
        echo "wp_post:<br>";
        var_dump($wp_post);
        echo "post_id:<br>";
        var_dump($post_id);
        echo "</pre><hr>";

        return;
    }

//upload imgage without attaching it to a certain post
    $media_upload = upload_file( $local_file_name, $local_file_path, $yyyymm);

    if(!$is_photo){
        $video_thumb_file_path = generate_thumbnail_from_video($local_file_name, $local_file_path, $taken_at, $ig_temp_directory);

        var_dump($video_thumb_file_path);
    }

//Attach uploaded file to post
    $uploaded_file_path = $media_upload['file'];
    $uploaded_url = $media_upload['url'];
    $uploaded_file_name = end(explode('/',$uploaded_file_path));
    if($is_photo) {
        $attach_id = attach_file($uploaded_file_path, $uploaded_file_name, $post_id, true);
    }
    if(!$is_photo){
        $attach_id = attach_file($uploaded_file_path, $uploaded_file_name, $post_id, false);
    }

//If photo set as featured image (adding img. to post content instead)
    /*if($is_photo){
        set_post_thumbnail( $post_id, $attach_id );
    }*/

//Add uploaded file to post content

    $post_with_image_or_video = array(
        'ID'           => $post_id,
        'post_content' => ''
    );

    if($is_photo){
        $post_with_image_or_video['post_content'] = '<figure><img src="' . $uploaded_url . '"></figure>' . $wp_post['post_content'];
    }
    if(!$is_photo){
        $post_with_image_or_video['post_content'] = '<video controls><source src="' . $uploaded_url . '" type="video/mp4"></video>' . $wp_post['post_content'];
    }

    $updpost = wp_update_post( $post_with_image_or_video );
    
    if (!$updpost>0) {
        var_dump("Updating post failed");
        var_dump($updpost);
        var_dump($photos);
        var_dump($wp_post);
        var_dump($post_id);
    }
}

function upload_file( $file_name, $file_path, $yyyymm ){
    $file_bits = file_get_contents($file_path);

    if ($file_bits === false) {
        echo "<pre>Reading file failed. file_path: " . $file_path . "</pre>";
        var_dump($file_bits);
        return false;
    }

    $uploaded = wp_upload_bits($file_name, null, $file_bits, $yyyymm);
    if ( array_key_exists( 'error', $uploaded )) {
        echo "<pre>Uploading to WP failed";
        var_dump($uploaded);
        echo "</pre>";
        return false;
    }

    return $uploaded;
}

function attach_file( $file_path, $file_name, $parent_post_id, $is_photo ) {
    $filetype = wp_check_filetype( basename( $file_path ) );
    $wp_upload_dir = wp_upload_dir();

    $attachment = array(
        'post_title'     => basename( $file_name ),
        'post_content'   => '',
        'post_status'    => 'inherit',
        'post_mime_type' => $filetype['type'],
    );

    $attach_id = wp_insert_attachment( $attachment, $file_path, $parent_post_id );

    if($is_photo){
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );
    }
    
    return $attach_id;
}

function generate_thumbnail_from_video($local_file_name, $local_video_file_path, $taken_at, $ig_temp_directory) {
    $temp_image_file_path = $ig_temp_directory . "/" . $local_file_name . '.jpg';

    $ffmpeg_path = '/usr/local/bin/ffmpeg';

    // timestamp: HH:MM:SS.fff
    $ffmpeg_cmd = "$ffmpeg_path -i $local_video_file_path -ss 00:00:01.000 -y -vframes 1 $temp_image_file_path";
    shell_exec($ffmpeg_cmd);

    return $temp_image_file_path;
}

?>
