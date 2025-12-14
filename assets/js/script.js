/* global wcsre_params */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // OPEN MODAL
    $("body").on("click", "#wcsre-open-modal", function (e) {
      e.preventDefault();
      const currentSlug = $("#wcsre_selected_location").val();

      $('input[name="wcsre_location_select"]').prop("checked", false);

      if (currentSlug) {
        $(
          'input[name="wcsre_location_select"][value="' + currentSlug + '"]'
        ).prop("checked", true);
      }

      $("#wcsre-modal").css("display", "flex").hide().fadeIn(200);
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
      const $selectedRadio = $('input[name="wcsre_location_select"]:checked');

      if ($selectedRadio.length) {
        const selectedSlug = $selectedRadio.val();
        const selectedLabel = $selectedRadio.data("label");

        $("#wcsre_selected_location").val(selectedSlug);
        $("#wcsre-open-modal").text("Updating...");
        $("#wcsre-modal").fadeOut(200);

        // STEP 1: Custom AJAX to save session
        $.ajax({
          type: "POST",
          url: wcsre_params.ajax_url,
          data: {
            action: "wcsre_update_session",
            security: wcsre_params.nonce,
            location: selectedSlug,
          },
          success: function (response) {
            if (response.success) {
              // STEP 2: Trigger WooCommerce update
              $("body").trigger("update_checkout");

              // Visual feedback
              setTimeout(function () {
                $("#wcsre-open-modal").text(selectedLabel);
                if ($("#wcsre-selected-display").length) {
                  $("#wcsre-selected-display").text("✓ " + selectedLabel);
                }
              }, 1500);
            } else {
              window.alert("Error updating location");
              $("#wcsre-open-modal").text("Try Again");
            }
          },
          error: function () {
            window.alert("Connection error");
            $("#wcsre-open-modal").text("Try Again");
          },
        });
      } else {
        window.alert("Please select a delivery area.");
      }
    });
  });
})(jQuery);
