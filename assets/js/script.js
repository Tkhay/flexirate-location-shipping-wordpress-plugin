/* global locsh_for_woocom_params */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // OPEN MODAL
    $("body").on("click", "#locsh-for-woocom-open-modal", function (e) {
      e.preventDefault();
      const currentSlug = $("#locsh_for_woocom_selected_location").val();

      $('input[name="locsh_for_woocom_location_select"]').prop("checked", false);

      if (currentSlug) {
        $('input[name="locsh_for_woocom_location_select"][value="' + currentSlug + '"]').prop(
          "checked",
          true
        );
      }

      $("#locsh-for-woocom-modal").css("display", "flex").hide().fadeIn(200);
    });

    // CLOSE MODAL
    $("body").on("click", "#locsh-for-woocom-close-modal", function (e) {
      e.preventDefault();
      $("#locsh-for-woocom-modal").fadeOut(200);
    });

    $(window).on("click", function (e) {
      if (e.target.id === "locsh-for-woocom-modal") {
        $("#locsh-for-woocom-modal").fadeOut(200);
      }
    });

    // CONFIRM SELECTION (TWO-STEP AJAX)
    $("body").on("click", "#locsh-for-woocom-confirm-selection", function (e) {
      e.preventDefault();
      const $selectedRadio = $('input[name="locsh_for_woocom_location_select"]:checked');

      if ($selectedRadio.length) {
        const selectedSlug = $selectedRadio.val();
        const selectedLabel = $selectedRadio.data("label");

        $("#locsh_for_woocom_selected_location").val(selectedSlug);
        $("#locsh-for-woocom-open-modal").text("Updating...");
        $("#locsh-for-woocom-modal").fadeOut(200);

        // STEP 1: Custom AJAX to save session
        $.ajax({
          type: "POST",
          url: locsh_for_woocom_params.ajax_url,
          data: {
            action: "locsh_for_woocom_update_session",
            security: locsh_for_woocom_params.nonce,
            location: selectedSlug,
          },
          success: function (response) {
            if (response.success) {
              // STEP 2: Trigger WooCommerce update
              $("body").trigger("update_checkout");

              // Visual feedback
              setTimeout(function () {
                $("#locsh-for-woocom-open-modal").text(selectedLabel);
                if ($("#locsh-for-woocom-selected-display").length) {
                  $("#locsh-for-woocom-selected-display").text("✓ " + selectedLabel);
                }
              }, 1500);
            } else {
              window.alert("Error updating location");
              $("#locsh-for-woocom-open-modal").text("Try Again");
            }
          },
          error: function () {
            window.alert("Connection error");
            $("#locsh-for-woocom-open-modal").text("Try Again");
          },
        });
      } else {
        window.alert("Please select a delivery area.");
      }
    });
  });
})(jQuery);
