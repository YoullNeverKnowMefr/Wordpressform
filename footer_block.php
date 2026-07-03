
<?php wp_footer(); ?>
<script type="text/javascript"
  src="<?php echo get_template_directory_uri() ?>/js/front.js?ver=1778595962"></script>
<?php if(file_exists(dirname( __FILE__ ).'/mailer.php') && !function_exists("wtw_forms_extentions")){ include_once 'mailer.php'; } ?>
<?php if(function_exists('get_field')) { echo get_field('footer_code', 'option'); } ?>
<?php if(file_exists(dirname( __FILE__ ).'/footer_code.php')){ include_once 'footer_code.php'; } ?>
<script type="text/javascript"
  src="<?php echo get_template_directory_uri() ?>/js/shop.js?ver=1778595962"></script>
<script src="https://cdn.prod.website-files.com/gsap/3.15.0/gsap.min.js" type="text/javascript"></script><script src="https://unpkg.com/simplebar@latest/dist/simplebar.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/selectize.js/0.15.2/js/selectize.min.js" integrity="sha512-IOebNkvA/HZjMM7MxL0NYeLYEalloZ8ckak+NDtOViP7oiYzG5vn6WVXyrJDiJPhl4yRdmNAG49iuLmhkUdVsQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>