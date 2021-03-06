<?php if( !defined('WPINC') ) die;
/** Admin Donor's info page template */

/** @var $this Leyka_Admin_Setup */

try {
    $donor = new Leyka_Donor(absint($_GET['donor']));
} catch(Exception $e) {
    wp_die($e->getMessage());
}

wp_nonce_field('leyka_save_donor_tags', 'leyka_save_donor_tags_nonce');

post_tags_meta_box($donor, array(
    'args' => array(
        'taxonomy' => Leyka_Donor::DONORS_TAGS_TAXONOMY_NAME, 
    ),
));
?>