<?php
/*
Template name: Каталог мероприятий тура
*/
?>
    <!DOCTYPE html>
<html data-wf-page="69c9abc7b278d0a422850eba" data-wf-site="5e7142bca461d1651b7ea6fb" lang="ru-RU">
	<?php get_template_part("header_block", ""); ?>
	<body>
<?php if(function_exists('get_field')) { echo get_field('body_code', 'option'); } ?>

		<div class="section loc-tour">
			<div class="padding-global">
				<div class="container-medium">
					<div class="padding-section-large-48">
						<div class="loc-tour_component">
							<div class="section_title">
								<div class="h2-style">Ближайшие интенсивы <span class="primary-light">в других городах</span></div>
								<div class="loc-tour_wrap">
									<div class="loc-tour_filter-box">
										<div class="filter-month active">
											<div>Январь</div>
										</div>
										<div class="filter-month">
											<div>Февраль</div>
										</div>
										<div class="filter-month">
											<div>Март</div>
										</div>
									</div>
									<div class="loc-tour_cards">
										<div class="city-colomns-list"><?php $rotation = 0; if(have_posts()) : while(have_posts()) : the_post(); $rotation === 0 ? $rotation = 1 : $rotation++; ?><?php $product = wc_get_product(get_the_ID()); ?><a href="<?php the_permalink(); ?>" target="_blank" class="loc-tour_town-card w-inline-block" data-content="query_item"><div class="w-layout-vflex box-info-2"><div class="loc-tour_wrap-info"><div class="calendar_month-4"><img width="18" height="20" alt="<?php echo !empty($field['alt']) ? esc_attr($field['alt']) : ''; ?>" src="<?php $field = get_field('ikonka_data'); if(isset($field['url'])){ echo($field['url']); }elseif(is_numeric($field)){ echo(wp_get_attachment_image_url($field, 'full')); }else{ echo($field); } ?>" loading="lazy" class="calendar_month-5"></div><div class="loc-tour_date"><?php echo get_field('data') ?></div></div><div class="loc-tour_wrap-info"><div class="location_on-5"><img width="19" height="24" alt="<?php echo !empty($field['alt']) ? esc_attr($field['alt']) : ''; ?>" src="<?php $field = get_field('ikonka_geo'); if(isset($field['url'])){ echo($field['url']); }elseif(is_numeric($field)){ echo(wp_get_attachment_image_url($field, 'full')); }else{ echo($field); } ?>" loading="lazy" class="location_on-6"></div><div class="loc-tour_city"><?php echo get_field('gorod') ?></div></div><div class="loc-tour_master-wrap"><div class="loc-tour_master"><?php echo get_field('veduschij') ?></div></div></div><div class="icon_arrow-link w-embed"><svg style="vertical-align: bottom;" width="100%" viewbox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="16" transform="matrix(-1 0 0 1 32 0)" fill="#D1DACE"></rect><path d="M13.8652 10.3999L19.1986 15.9999L13.8652 21.5999" stroke="#788373" stroke-width="1.06667" stroke-linecap="round"></path></svg></div></a><?php endwhile; ?><?php else : ?><?php endif; wp_reset_postdata(); ?></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		
		
		
		
		
	
<!-- FOOTER CODE --><?php get_template_part("footer_block", ""); ?>
<script type="text/javascript" src="<?php bloginfo('template_url'); ?>/js/archive-tour.js?ver=1778595962"></script></body>
</html>
