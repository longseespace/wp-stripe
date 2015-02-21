
jQuery(document).ready(function($) {

  $.fn.serializeObject = function(){
    var o = {};
    var a = this.serializeArray();
    $.each(a, function() {
        if (o[this.name] !== undefined) {
            if (!o[this.name].push) {
                o[this.name] = [o[this.name]];
            }
            o[this.name].push(this.value || '');
        } else {
            o[this.name] = this.value || '';
        }
    });
    return o;
  };

  $('.selection-buttons > div.item').click(function(e) {
    e.preventDefault();
    $('#error-message > span').html('');
    
    var $this = $(this);
    $('.selection-buttons > div.item').removeClass('selected');
    $this.addClass('selected');
    $this.children('input').attr('checked', 'checked');
  });

  $('#item-2d').click(function(e) {
    e.preventDefault();
    $('.vc_box_shadow_3d_wrap img').attr('src', '/wp-content/uploads/2014/11/6-300x300.jpg');
  });

  $('#item-3d').click(function(e) {
    e.preventDefault();
    $('.vc_box_shadow_3d_wrap img').attr('src', '/wp-content/uploads/2014/11/1-300x300.jpg');
  });

  var resetStripeForm = function() {
    $("#wp-stripe-payment-form").get(0).reset();
    $('input').removeClass('stripe-valid stripe-invalid');
  }

  $('#check-out').click(function(e) {
    e.preventDefault();

    $('#error-message > span').html('');

    var $this = $(this);

    if ($this.hasClass('disabled')) {
      return;
    };

    if ($('#item-2d').hasClass('selected')) {
      is2D = true;
    } else {
      is2D = false;
    }

    // adjust stripe form
    var quantity = +$('#quantity').val();
    var amount = 0;
    var name = "InAiR";
    var desc = '';

    if (is2D) {
      amount = 149.99 * quantity + 30;
      if (quantity == 1) {
        desc = "$" + amount + " ($149.99 + $30 shipping)";
      } else {
        desc = "$" + amount + " (" + quantity + " x $149.99 + $30 shipping)";
      }
    } else {
      amount = 179.99 * quantity + 30;
      if (quantity == 1) {
        desc = "$" + amount + " ($179.99 + $30 shipping)";
      } else {
        desc = "$" + amount + " (" + quantity + " x $179.99 + $30 shipping)";
      }
    }
    
    var handler = StripeCheckout.configure({
      key: 'pk_test_CqFFnedgvxP0C3scxFOXCUIU',
      image: '/wp-content/uploads/2014/11/logo_ios.png',
      token: function(token, args) {
        // Use the token to create the charge with a server-side script.
        // You can access the token ID with `token.id`
        console.log(token, args);

        $('#check-out').addClass('disabled');
        $('#check-out > span').text('Processing...');

        // At this point the Stripe checkout overlay is validated and submitted.

        $('#wp_stripe_name').val(token.card.name);
        $('#wp_stripe_email').val(token.email);
        $('#wp_stripe_amount').val(amount);
        $('#wp_stripe_comment').val(desc);

        var form$ = $("#wp-stripe-payment-form");
        form$.append("<input type='hidden' name='stripeToken' value='" + token.id + "' />");

        var newStripeForm = form$.serializeObject();

        newStripeForm.shipping_address_apt = args.shipping_address_apt;
        newStripeForm.shipping_address_city = args.shipping_address_city;
        newStripeForm.shipping_address_country = args.shipping_address_country;
        newStripeForm.shipping_address_country_code = args.shipping_address_country_code;
        newStripeForm.shipping_address_line1 = args.shipping_address_line1;
        newStripeForm.shipping_address_state = args.shipping_address_state;
        newStripeForm.shipping_address_zip = args.shipping_address_zip;
        newStripeForm.shipping_name = args.shipping_name;

        newStripeForm.quantity = quantity;
        newStripeForm.model = is2D ? '2d' : '3d';

        $.ajax({
          type : "post",
          dataType : "json",
          url : ajaxurl,
          data : newStripeForm,
          success: function(response) {
            $('#check-out').removeClass('disabled');

            if (response.success) {
              $('#check-out > span').text('Completed');
              document.location = "/";

              $('.wp-stripe-details').prepend(response);
              $('.stripe-submit-button').prop("disabled", false).css("opacity","1.0");
              $('.stripe-submit-button .spinner').fadeOut("slow");
              $('.stripe-submit-button span').removeClass('spinner-gap');
            } else {
              $('#error-message > span').html('Error: ' + response.error);
              $('#check-out > span').text('Check Out');
            }
            
            resetStripeForm();
          }
        });
      }
    });
    
    

    handler.open({
     name: name,
     description: desc,
     billingAddress: true,
     shippingAddress: true,
     amount: amount * 100
    });

    // Close Checkout on page navigation
    $(window).on('popstate', function() {
      handler.close();
    });
  });
});