jQuery(document).ready(function ($) {
  // OPEN MODAL
  $("body").on("click", "#wcsre-open-modal", function (e) {
    e.preventDefault();
    var current_slug = $("#wcsre_selected_location").val();
    $('input[name="wcsre_location_select"]').prop("checked", false);
    if (current_slug) {
      $(
        'input[name="wcsre_location_select"][value="' + current_slug + '"]'
      ).prop("checked", true);
    }
    $("#wcsre-modal").fadeIn(200);
  });

  // CLOSE MODAL
  $("body").on("click", "#wcsre-close-modal", function (e) {
    e.preventDefault();
    $("#wcsre-modal").fadeOut(200);
  });
  $(window).on("click", function (e) {
    if (e.target.id === "wcsre-modal") {
      $("#wcsre-modal").fadeOut(200);
    }
  });

  // CONFIRM SELECTION (TWO-STEP AJAX)
  $("body").on("click", "#wcsre-confirm-selection", function (e) {
    e.preventDefault();
    var selected_radio = $('input[name="wcsre_location_select"]:checked');

    if (selected_radio.length) {
      var selected_slug = selected_radio.val();
      var selected_label = selected_radio.data("label");

      $("#wcsre_selected_location").val(selected_slug);
      $("#wcsre-open-modal").text("Updating...");
      $("#wcsre-modal").fadeOut(200);

      // STEP 1: Custom AJAX to save session
      $.ajax({
        type: "POST",
        url: wcsre_params.ajax_url,
        data: {
          action: "wcsre_update_session",
          security: wcsre_params.nonce,
          location: selected_slug,
        },
        success: function (response) {
          if (response.success) {
            // STEP 2: Trigger WooCommerce update
            $("body").trigger("update_checkout");

            // Visual feedback
            setTimeout(function () {
              $("#wcsre-open-modal").text(selected_label);
              if ($("#wcsre-selected-display").length) {
                $("#wcsre-selected-display").text("✓ " + selected_label);
              }
            }, 1500);
          } else {
            alert("Error updating location");
            $("#wcsre-open-modal").text("Try Again");
          }
        },
        error: function () {
          alert("Connection error");
          $("#wcsre-open-modal").text("Try Again");
        },
      });
    } else {
      alert("Please select a delivery area.");
    }
  });
});
