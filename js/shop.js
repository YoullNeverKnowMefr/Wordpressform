
const custom_checkout = get_custom_checkout();
disable_qty_submit_in_cart();

jQuery(document).ready(function ($) {

  load_product();
  slider_filter();
  product_rating();
  product_qty_chnage();

  init_variations();
  select_variation_attributes();
  select_one_variation();
  product_var_select();
  variation_reset();
  set_var_price();

  cart_init();
  add_to_cart();
  change_cart_qty();
  cart_remove_all();

  payment_select();
  checkout_recalc();

  account_forms_actions();

  add_to_wl();
  wl_remove();
  wl_move();
  wl_copy();

})


function account_forms_actions() {

  $('[data-action=remove_user_avatar]').on('click', function () {
    $(this).hide();
    $('[data-content=user_avatar]').hide();
    ajaxs('ajaxs_remove_user_avatar');
  })

  $('[name=user_avatar]').on('change', function () {
    var fileName = $(this).val().split('\\').pop();
    let loaded_el = $('[data-content=user_avatar_file]');
    loaded_el.text(loaded_el.text().replace('%file%', fileName));
    loaded_el.show();
  })

  $('#login_form').submit(function (e) {
    e.preventDefault();
    ajaxs('wc_login', $(this),
      function (result) {
        if (result.error) {
          $('#login_form').siblings('.w-form-done').hide();
          $('#login_form').siblings('.w-form-fail').html(result.data).show();
        } else {
          var redirect = $('#login_form').attr('redirect');
          if (redirect !== undefined) {
            location.replace(redirect);
          } else {
            location.reload();
          }
        }
      });
  });

  $('#register_form').submit(function (e) {

    e.preventDefault();

    ajaxs('wc_register', $(this),
      function (result) {
        if (result.error) {
          $('#register_form').siblings('.w-form-done').hide();
          $('#register_form').siblings('.w-form-fail').show().children(":first").html(result.data);
        } else {
          var redirect = $('#register_form').attr('redirect');
          if (redirect !== undefined) {
            location.replace(redirect);
          } else {
            $('#register_form').siblings('.w-form-fail').hide();
            $('#register_form').siblings('.w-form-done').show();
          }
        }
      });

  });

  $('#recover_form').submit(function (e) {

    e.preventDefault();
    data = { subject: $(this).attr('data-subject'), message: $(this).attr('data-message'), foo: $(this) };
    ajaxs('wc_recover', data,
      function (result) {
        if (result.error) {
          $('#recover_form').siblings('.w-form-done').hide();
          $('#recover_form').siblings('.w-form-fail').html(result.data).show();
        } else {
          $('#recover_form').siblings('.w-form-fail').hide();
          $('#recover_form').siblings('.w-form-done').show();
        }
      });

  });

  $('[name=update_user]').submit(function (e) {
    e.preventDefault();
    var data = {};
    data.first_name = $(this).find('[name=account_first_name]').val();
    data.last_name = $(this).find('[name=account_last_name]').val();
    data.user_email = $(this).find('[name=account_email]').val();
    ajaxs('update_user', data);
  });

  $('[name=update_password]').submit(function (e) {
    e.preventDefault();
    var data = {};
    data.password_current = $(this).find('[name=password_current]').val();
    data.password_1 = $(this).find('[name=password_1]').val();
    data.password_2 = $(this).find('[name=password_2]').val();
    ajaxs('update_password', data);
  });

  $('[name=update_profile]').submit(function (e) {
    e.preventDefault();
    ajaxs('update_profile', $(this));
  });

  $('[name=update_billing]').submit(function (e) {
    e.preventDefault();
    ajaxs('update_billing', $(this));
  });

  $('[name=update_shipping]').submit(function (e) {
    e.preventDefault();
    ajaxs('update_shipping', $(this));
  });
}

function cart_init() {
  const cart_items_count = $('[data-content=cart_item]').length;
  if (cart_items_count > 0) {
    $("[data-content=cart_content]").show();
    $("[data-content=cart_empty]").hide();
    $('[data-wc=cart_count_wrapper]').show();
  } else {
    $("[data-content=cart_content]").hide();
    $("[data-content=cart_empty]").show();
    $('[data-wc=cart_count_wrapper]').hide();
  }
}

function cart_remove_all() {
  $('body').on('click', '[data-action=remove_all]', function (e) {
    e.preventDefault();
    ajaxs('cart_remove_all');
  })
}

function add_to_cart() {
  $('body').on('submit', '[data-name=add_to_cart],[data-name="change_cart"]', function (e) {

    e.preventDefault();

    let data = {};
    let item_data = {};
    let variation_attributes = {}
    let complete_select = true;

    const add_cart_el = $(this);
    const product_id = add_cart_el.attr('data-product-id');
    const variation_id = add_cart_el.attr('data-variation-id');

    let product_qty = 1;
    let product_qty_input = add_cart_el.find('[data-name=qty]');

    if (product_qty_input.length) {
      product_qty = product_qty_input.val();
    }

    $('form[name=add_to_cart][data-product-id=' + product_id + '] select[name^=attribute_pa_], form[name=add_to_cart][data-product-id=' + product_id + '] input:radio:checked[name^=attribute_pa_]').each(function () {
      attribute_name = $(this).attr('name');
      attribute_value = $(this).val();
      variation_attributes[attribute_name] = attribute_value;
      if (attribute_value === '') complete_select = false;
    });

    $('form[name=add_to_cart][data-product-id=' + product_id + '] select[name^=pa_], form[name=add_to_cart][data-product-id=' + product_id + '] input:radio:checked[name^=pa_], form[name=add_to_cart][data-product-id=' + product_id + '] input[type=text][name^=pa_]').each(function () {
      attribute_name = $(this).attr('name');
      attribute_value = $(this).val();
      item_data[attribute_name] = attribute_value;
    });

    data.product_id = product_id;
    data.qty = product_qty;
    data.variation_id = variation_id;
    data.variation_attributes = variation_attributes;
    data.item_data = item_data;

    product_qty_input.val(1);

    if (typeof (wtw_cart_data_modify) === "function") {
      data = wtw_cart_data_modify(data, add_cart_el);
    }

    ajaxs('add_to_cart', data, function () {
      setTimeout(() => {
        cart_init();

        if (typeof (wtw_after_add_to_cart) === 'function') {
          wtw_after_add_to_cart();
        } else {
          add_cart_el.siblings('.w-form-done').show().delay(3000).fadeOut();
        }

      }, 500);
    });

    return false;
  });
}

function change_cart_qty() {
  $('body').on('click', '[data-action=cart_qty_plus],[data-action=cart_qty_minus],[data-action=cart_product_remove]', function (e) {
    on_change_cart_qty($(this));
  });

  $('body').on('keyup', '[data-action="cart_product_qty"]', function (e) {
    on_change_cart_qty($(this));
  });
}

function on_change_cart_qty(action) {

  const cart_action = action.attr('data-action');
  const item_key = action.attr('data-key');

  let item_qty = null;

  if (cart_action === 'cart_product_qty') {
    item_qty = action.val();
  }

  var data = {
    key: item_key,
    qty: item_qty,
    cart_action: cart_action,
  };

  ajaxs('change_cart_qty', data, function () {

    setTimeout(cart_init, 500);

    if (custom_checkout) {
      $('#checkout').css('opacity', '0.3');
      ajaxs('recalc_checkout', $('#checkout_form'), function () {
        $('#checkout').css('opacity', '1');
      });
    }

    if (typeof (wtw_after_cart_data_modify) === "function") {
      data = wtw_after_cart_data_modify(data);
    }

    setTimeout(() => {
      $('#add_coupon').attr('action', location.pathname);
    }, 500)

  });
}

function disable_qty_submit_in_cart() {
  $('[data-action="cart_product_qty"]').parents('form').attr('action', 'cart_qty')
    .submit(function (e) {
      e.preventDefault();
    });
}

function slider_filter(){
  const slider_el = $('[data-price-slider]');
  if (slider_el.length && 'slider' in slider_el) {
    slider_el.slider({
      step: parseInt($("[data-ui-slider]").attr('data-ui-slider')),
      range: true,
      min: parseInt($("[name=min_price]").attr('data-value')),
      max: parseInt($("[name=max_price]").attr('data-value')),
      values: [parseInt($("[name=min_price]").val()), parseInt($("[name=max_price]").val())],
      slide: function (event, ui) {
        $("[name=min_price]").val(ui.values[0]).keyup();
        $("[name=max_price]").val(ui.values[1]).keyup();
      }
    });
  }
}

function payment_select() {
  $('[data-name="payment_method"]:first').prop('checked', true)
    .siblings('.w-form-formradioinput--inputType-custom').addClass('w--redirected-checked');

  $('[data-payment-desc]:first').show();

  $('[name=payment_method]').change(function () {
    $('[data-payment-desc]').hide();
    $('[data-payment-desc=' + $(this).val() + ']').show();
  });
}

function checkout_recalc() {
  if ($('[data-action=checkout]').length) {

    if ($('[name="shipping_method[0]"]:checked').length === 0) {
      $('[name="shipping_method[0]"]:first').prop('checked', true);
    }

    $('[name="shipping_method[0]"]:checked').siblings('.w-form-formradioinput--inputType-custom').addClass('w--redirected-checked');

    $('body').on('change', '#shipping_method,#shipping_methods input,#billing_country,#billing_states',
      function () {
        if (custom_checkout) {
          $('#checkout').css('opacity', '0.3');
          ajaxs('recalc_checkout', $('#checkout_form'), function () {
            $('#checkout').css('opacity', '1');
          });
        }
      });
  }
}

function get_custom_checkout() {
  return $('form[action="/checkout"]').length
}

function load_product() {
  $('body').on('click', '[data-load-product]', function () {
    var data = {};
    data.id = $(this).attr('data-product-id'); //prod
    data.part = $(this).attr('data-load-product');
    $('[data-part="' + data.part + '"]').hide();
    ajaxs('load_product', data, function (responce) {
      $('[data-part="' + data.part + '"]').show();
    });
  });
}

function product_qty_chnage() {

  $('body').on('click', '[data-action=product_qty_plus]', function () {
    product_qty = $(this).parents('form').find('[data-name=qty]');
    if (parseInt(product_qty.attr('data-qty-max')) > parseInt(product_qty.val())
      || product_qty.attr('data-qty-max') === undefined
      || product_qty.attr('data-qty-max') === '0'
    ) {
      product_qty.val(parseInt(product_qty.val()) + 1);
      product_qty.trigger('change');
    }
  });

  $('body').on('change', '[data-action=product_qty]', function () {

    const product_qty = $(this);
    const max_qty_el = product_qty.attr('data-qty-max');

    if (max_qty_el != undefined) {
      max_qty = parseInt(max_qty_el);
    } else {
      max_qty = 0;
    }

    cur_qty = parseInt(product_qty.val());

    if (max_qty < cur_qty && max_qty != 0) {
      product_qty.val(max_qty);
    }
  });

  $('body').on('click', '[data-action=product_qty_minus]', function () {
    const product_qty = $(this).parents('form').find('[data-name=qty]');
    const min_qty = product_qty.attr('data-qty-min');
    const cur_qty = parseInt(product_qty.val());
    if (cur_qty > min_qty) {
      product_qty.val(cur_qty - 1);
      product_qty.trigger('change');
    }
  });
}

function product_rating() {
  $('.comment-form-rating a').click(function (e) {
    cur_index = $(this).text();
    $(this).siblings().each(function () {
      if ($(this).text() < cur_index) {
        $(this).addClass('filled');
      } else {
        $(this).removeClass('filled');
      }
    });
  });
}

function init_variations() {

  let products = $('[data-content=product],[data-content=query_item]');
  let variation_attributes = {};
  let product_id = 0;

  if ($('[data-action=add_to_cart] [data-attribute_name]').length) {

    if (window.location.search.indexOf('attribute_pa') != -1) {

      let cart_el = $('[data-action="add_to_cart"]');
      if (cart_el.length === 0) return;

      product_id = cart_el.attr('data-product-id');

      attributes = window.location.search.replace('?', '').split('&');
      for (var key in attributes) {
        attribute = attributes[key].split('=');
        variation_attributes[attribute[0].replace('attribute_', '')] = attribute[1];
      }
      ajaxs('ajaxs_load_variation', { id: product_id, variation_attributes: variation_attributes },
        function (response) { update_variation(response); });

    } else {

      products.each(function () {
        let cart_el = $(this).find('[data-action="add_to_cart"]');

        if (cart_el.length === 0) {
          return;
        }

        if ($(this).find('[data-action=add_to_cart] [data-attribute_name]').length === 0) {
          return;
        }

        product_id = cart_el.attr('data-product-id');

        ajaxs('ajaxs_load_variation', { id: product_id, variation_attributes: '' },
          function (response) { update_variation(response); });

      })

    }

  }
}

function select_variation_attributes() {
  $('body').on('change', '[name=add_to_cart] select[data-attribute_name],[name=add_to_cart] input[data-attribute_name]', function () {

    const form_el = $(this).parents('form');

    let product_el = $(this).parents('[data-content=query_item]');
    if (product_el.length === 0) {
      product_el = $('[data-content=product]');
    }

    form_el.siblings('.w-form-done').hide();

    if ($(this).attr('type') === 'radio') {
      $(this).parent().parent().find('label').removeClass('active');
      $(this).parent().addClass('active');
    }

    let product_id = form_el.attr('data-product-id');
    let variation_attributes = {};
    let complete_select = true;

    form_el.find('select, input:radio:checked').each(function () {
      attribute_name = $(this).attr('name');
      attribute_value = $(this).val();
      variation_attributes[attribute_name] = attribute_value;
      if (attribute_value === '') complete_select = false;
    });

    if (complete_select) {
      product_el.find('[data-content="product"]').css('opacity', 0.2);
      product_el.find('[data-action=variation_preload]').show();

      ajaxs('ajaxs_load_variation', { id: product_id, variation_attributes: variation_attributes }, function (response) {
        update_variation(response);
      });
    } else {
      product_el.find('[data-content="product"]').css('opacity', 0.2);
      product_el.find('[data-action=variation_preload]').show();

      ajaxs('ajaxs_load_variation', { id: product_id, variation_attributes: variation_attributes }, function (response) {
        update_variation(response);
      });
    }
  });
}

function update_variation(cur_variation) {

  const form_el = $(`[data-product-id=${cur_variation.product_id}]`);

  let product_el = $('[data-content=product]');

  if (product_el.length === 0) {
    product_el = form_el.parents('[data-content=query_item]');
  }

  if (cur_variation.id === 0) {

    product_el.find('[data-content^=var_]').hide();
    product_el.find('[data-content=var_stocked]').hide();
    product_el.find('[data-content=var_not_stocked]').show();
    form_el.find('[type=submit]').hide();

    if (cur_variation.attributes_complete) {
      product_el.find('[data-content=var_not_stocked]').show();
    }

    product_el.find('[data-content="var_image"]').show();

    if (cur_variation.parent_image_url) {
      product_el.find('[data-content=var_image]').attr('src', cur_variation.parent_image_url);
      product_el.find('[data-content=var_bg_image]').attr('style', 'background-image: url(' + cur_variation.parent_image_url + ');');
    }

  } else {

    product_el.find('[data-content^=var_]').show();
    product_el.find('[data-content=var_stocked]').show();
    product_el.find('[data-content=var_not_stocked]').hide();
    product_el.find('[data-content=var_sku]').text(cur_variation.sku).show();
    product_el.find('[data-content=var_stock]').text(cur_variation.stock_quantity).show();

    form_el.attr('data-variation-id', cur_variation.id);
    form_el.find('[type=submit]').show();

    const cur_qty = parseInt(form_el.find('[data-name=qty]').val());
    const qty_min = form_el.find('[data-name=qty]').attr('data-qty-min');
    form_el.find('[data-name=qty]').attr('data-qty-max', cur_variation.stock_quantity);

    if (cur_variation.stock_quantity != null && cur_qty > cur_variation.stock_quantity) {
      form_el.find('[data-name=qty]').val(qty_min);
    }

    if (cur_variation.stock_quantity) {
      product_el.find('[data-content=var_stock_qty]').show();
    } else {
      product_el.find('[data-content=var_stock_qty]').hide();
    }

    product_el.find('[data-content=var_weight]').text(cur_variation.weight).show();
    product_el.find('[data-content=var_length]').text(cur_variation.length).show();
    product_el.find('[data-content=var_width]').text(cur_variation.width).show();
    product_el.find('[data-content=var_height]').text(cur_variation.height).show();
    product_el.find('[data-content=var_desc]').text(cur_variation.description).show();

    if (cur_variation.price !== '') {
      product_el.find('[data-content=var_price],[data-content=product_price]')
        .html(cur_variation.price).attr('data-price', cur_variation.clean_price).show();
    } else {
      product_el.find('[data-content=var_price],[data-content=product_price]').html('').hide();
    }

    if (cur_variation.sale_price !== '') {
      product_el.find('[data-content=var_price_sale],[data-content=product_price_sale]')
        .html(cur_variation.sale_price).attr('data-price', cur_variation.clean_sale_price).show();
    } else {
      product_el.find('[data-content=var_price_sale],[data-content=product_price_sale]').html('').hide();
    }

    if (cur_variation.regular_price !== '') {
      product_el.find('[data-content=var_price_regular],[data-content=product_price_regular]')
        .html(cur_variation.regular_price).attr('data-price', cur_variation.clean_regular_price).show();
    } else {
      product_el.find('[data-content=var_price_regular],[data-content=product_price_regular]').html('').hide();
    }

    if (cur_variation.image_url) {
      product_el.find('[data-content=var_image]').attr('src', cur_variation.image_url);

      product_el.find('[data-content=var_bg_image]').attr('style', 'background-image: url(' + cur_variation.image_url + ');');

      product_el.find('[data-content=var_lbox_image]').each(function () {

        var lbox_script = $(this).find('script');

        if (lbox_script[0]) {

          var lbox_data = JSON.parse(lbox_script.html());

          lbox_data.items[0].url = cur_variation.image_url;
          lbox_script.html(JSON.stringify(lbox_data));

          wtw_webflow_init();
        }
      });
    }

    if (cur_variation.stock_status === 'onbackorder') {
      product_el.find('[data-content=var_stocked]').hide();
      product_el.find('[data-content=var_in_stock]').hide();
      product_el.find('[data-content=var_not_stocked]').hide();
      product_el.find('[data-content=var_backorder]').show();
    } else if (cur_variation.stock_status === 'instock') {
      product_el.find('[data-content=var_stocked]').show();
      product_el.find('[data-content=var_in_stock]').show();
      product_el.find('[data-content=var_not_stocked]').hide();
      product_el.find('[data-content=var_backorder]').hide();
    } else {
      product_el.find('[data-content=var_stocked]').hide();
      product_el.find('[data-content=var_in_stock]').hide();
      product_el.find('[data-content=var_not_stocked]').show();
      product_el.find('[data-content=var_backorder]').hide();
    }
  }

  let default_attributes = cur_variation.attributes;

  for (var key in default_attributes) {
    product_el.find(`select[name=attribute_${key}] [value="${default_attributes[key]}"]`).prop("selected", true);
    product_el.find(`[type=radio][name=attribute_${key}][value="${default_attributes[key]}"]`).each(function () {
      $(this).prop("checked", true);
      $(this).siblings('.w-form-formradioinput--inputType-custom').addClass('w--redirected-checked');
      $(this).parent().addClass('active');
    });
  }

  // Расширяющий хук для обновления вариации
  if (typeof (wtw_after_variaton_changed) === "function") {
    wtw_after_variaton_changed(cur_variation);
  }

  product_el.find('[data-content="product"]').css('opacity', 1);
  product_el.find('[data-action=variation_preload]').hide();

}

function set_var_price() {
  $('[data-action=product_var_select]').each(function () {
    if ($(this).find('option').length) {
      product_content = $(this).parents('[data-content=product]');
      product_content.find('[data-variation-id]').hide();
      variation_select = product_content.find('[data-action=product_var_select] option');
      if (variation_select.length === 0) return;
      variation_id = variation_select[0].value;
      if (variation_id !== undefined && variation_id !== '') {
        product_content.find('[data-variation-id=' + variation_id + ']').show();
        if ($(this).find('option[value=' + variation_id + ']').attr('data-in-stock') === '1') {
          product_content.find('[data-var-in-stock=1]').show();
          product_content.find('[data-var-in-stock=0]').hide();
        } else {
          product_content.find('[data-var-in-stock=0]').show();
          product_content.find('[data-var-in-stock=1]').hide();
        }
      }
    }
  });
}

function select_one_variation() {
  $('body').on('change', '[data-action="var_select"]', function () {

    let value = $(this).val();
    let form = $(this).parents('form');
    let option = $(this).find('option[value="' + value + '"]');
    let price = option.attr('data-price');
    let price_regular = option.attr('data-price-regular');
    let stock_status = option.attr('data-stock-status');

    form.attr('data-variation-id', value); //var

    if (stock_status === 'backorder') {
      form.find('[data-content=var_stocked]').show();
      form.find('[data-content=var_in_stock]').hide();
      form.find('[data-content=var_not_stocked]').hide();
      form.find('[data-content=var_backorder]').show();
    } else if (stock_status === 'in_stock') {
      form.find('[data-content=var_stocked]').show();
      form.find('[data-content=var_in_stock]').show();
      form.find('[data-content=var_not_stocked]').hide();
      form.find('[data-content=var_backorder]').hide();
    } else if (stock_status === 'out_stock') {
      form.find('[data-content=var_stocked]').hide();
      form.find('[data-content=var_in_stock]').hide();
      form.find('[data-content=var_not_stocked]').show();
      form.find('[data-content=var_backorder]').hide();
    }

    if (price === price_regular) {
      form.find(
        '[data-content="product_price_sale"],[data-content="product_price_regular"]').hide();
      form.find('[data-content="product_price"]').show().text(price);
    } else {
      form.find(
        '[data-content="product_price"]').hide();
      form.find('[data-content="product_price_sale"]').show().text(price);
      form.find('[data-content="product_price_regular"]').show().text(price_regular);
    }
  });

  $('[data-action="var_select"]').trigger('change');
}

function variation_choice() {
  $('body').on('change', '[data-action="var_choice"]', function () {
    let form = $(this).parents('form');
    let label = $(this).parents('label');
    let price = $(this).attr('data-price');
    let price_regular = $(this).attr('data-price-regular');
    let stocked = $(this).attr('data-stocked');

    form.attr('data-variation-id', $(this).val()); //var

    if (stocked === '1') {
      form.find('[data-content=var_stocked]').show();
      form.find('[data-content=var_not_stocked]').hide();
    } else {
      form.find('[data-content=var_stocked]').hide();
      form.find('[data-content=var_not_stocked]').show();
    }

    if (price === price_regular) {
      form.find(
        '[data-content="product_price_sale"],[data-content="product_price_regular"]').hide();
      form.find('[data-content="product_price"]').show().text(price);
    } else {
      form.find(
        '[data-content="product_price"]').hide();
      form.find('[data-content="product_price_sale"]').show().text(price);
      form.find('[data-content="product_price_regular"]').show().text(price_regular);
    }

    label.siblings().removeClass('active');
    label.addClass('active');
  });
}

function product_var_select() {
  $('[data-action=product_var_select]').change(function () {
    product_content = $(this).parents('[data-content=product]');
    $(this).parents('form').attr('data-product-id', $(this).val()); //prod
    product_content.find('[data-variation-id]').hide();
    product_content.find('[data-variation-id=' + $(this).val() + ']').show();
    if ($(this).find('option[value=' + $(this).val() + ']').attr('data-in-stock') === '1') {
      product_content.find('[data-var-in-stock=1]').show();
      product_content.find('[data-var-in-stock=0]').hide();
    } else {
      product_content.find('[data-var-in-stock=0]').show();
      product_content.find('[data-var-in-stock=1]').hide();
    }
  })
}

function variation_reset() {
  $('[data-action=variation_reset]').click(function () {
    $('form[name=add_to_cart] select').each(function () {
      $(this).val('');
      $('[data-content=var_not_stocked]').hide();
      //$('[name=add_to_cart]').siblings('.w-form-fail').hide();
    });
    $('[data-content^=var_]').hide();
  });
}

function add_to_wl() {
  $('body').on('submit', '[data-name=add_to_wl]', function (e) {
    e.preventDefault();
    var data = {};
    data.id = $(this).attr('data-product-id'); //prod
    ajaxs('add_to_wl', data);
    $(this).siblings('.w-form-done').show().delay(3000).fadeOut();
    return false;
  });
  $('body').on('click', '[data-action=add_to_wl]', function (e) {
    e.preventDefault();
    var data = {};
    data.id = $(this).attr('data-product-id'); //prod
    ajaxs('add_to_wl', data);
    return false;
  });
}

function wl_remove() {
  $('body').on('click', '[data-action=wl_remove]', function (e) {
    e.preventDefault();
    var data = {};
    data.id = [];
    data.id[0] = $(this).attr('data-product-id'); //prod
    ajaxs('wl_remove', data);
    return false;
  })
}

function wl_move() {
  $('body').on('click', '[data-action=wl_move]', function (e) {
    e.preventDefault();
    var data = {};
    data.id = [];
    data.id[0] = $(this).attr('data-product-id'); //prod
    ajaxs('wl_move', data);
    return false;
  })
}

function wl_copy() {
  $('body').on('click', '[data-action=wl_copy]', function (e) {
    e.preventDefault();
    var data = {};
    data.id = [];
    data.id[0] = $(this).attr('data-product-id'); //prod
    ajaxs('wl_copy', data);
    return false;
  })
}

