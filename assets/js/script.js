/* global frls_params */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // OPEN MODAL
    $("body").on("click", "#frls-open-modal", function (e) {
      e.preventDefault();
      const currentSlug = $("#frls_selected_location").val();

      $('input[name="frls_location_select"]').prop("checked", false);

      if (currentSlug) {
        $(
          'input[name="frls_location_select"][value="' + currentSlug + '"]'
        ).prop("checked", true);
      }

      $("#frls-modal").css("display", "flex").hide().fadeIn(200);
    });

    // CLOSE MODAL
    $("body").on("click", "#frls-close-modal", function (e) {
      e.preventDefault();
      $("#frls-modal").fadeOut(200);
    });

    $(window).on("click", function (e) {
      if (e.target.id === "frls-modal") {
        $("#frls-modal").fadeOut(200);
      }
    });

    // CONFIRM SELECTION (TWO-STEP AJAX)
    $("body").on("click", "#frls-confirm-selection", function (e) {
      e.preventDefault();
      const $selectedRadio = $('input[name="frls_location_select"]:checked');

      if ($selectedRadio.length) {
        const selectedSlug = $selectedRadio.val();
        const selectedLabel = $selectedRadio.data("label");

        $("#frls_selected_location").val(selectedSlug);
        $("#frls-open-modal").text("Updating...");
        $("#frls-modal").fadeOut(200);

        // STEP 1: Custom AJAX to save session
        $.ajax({
          type: "POST",
          url: frls_params.ajax_url,
          data: {
            action: "frls_update_session",
            security: frls_params.nonce,
            location: selectedSlug,
          },
          success: function (response) {
            if (response.success) {
              // STEP 2: Trigger WooCommerce update
              $("body").trigger("update_checkout");

              // Visual feedback
              setTimeout(function () {
                $("#frls-open-modal").text(selectedLabel);
                if ($("#frls-selected-display").length) {
                  $("#frls-selected-display").text("✓ " + selectedLabel);
                }
              }, 1500);
            } else {
              window.alert("Error updating location");
              $("#frls-open-modal").text("Try Again");
            }
          },
          error: function () {
            window.alert("Connection error");
            $("#frls-open-modal").text("Try Again");
          },
        });
      } else {
        window.alert("Please select a delivery area.");
      }
    });
  });
})(jQuery);
