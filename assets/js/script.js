/* global ls_params */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // OPEN MODAL
    $("body").on("click", "#ls-open-modal", function (e) {
      e.preventDefault();
      const currentSlug = $("#ls_selected_location").val();

      $('input[name="ls_location_select"]').prop("checked", false);

      if (currentSlug) {
        $('input[name="ls_location_select"][value="' + currentSlug + '"]').prop(
          "checked",
          true
        );
      }

      $("#ls-modal").css("display", "flex").hide().fadeIn(200);
    });

    // CLOSE MODAL
    $("body").on("click", "#ls-close-modal", function (e) {
      e.preventDefault();
      $("#ls-modal").fadeOut(200);
    });

    $(window).on("click", function (e) {
      if (e.target.id === "ls-modal") {
        $("#ls-modal").fadeOut(200);
      }
    });

    // CONFIRM SELECTION (TWO-STEP AJAX)
    $("body").on("click", "#ls-confirm-selection", function (e) {
      e.preventDefault();
      const $selectedRadio = $('input[name="ls_location_select"]:checked');

      if ($selectedRadio.length) {
        const selectedSlug = $selectedRadio.val();
        const selectedLabel = $selectedRadio.data("label");

        $("#ls_selected_location").val(selectedSlug);
        $("#ls-open-modal").text("Updating...");
        $("#ls-modal").fadeOut(200);

        // STEP 1: Custom AJAX to save session
        $.ajax({
          type: "POST",
          url: ls_params.ajax_url,
          data: {
            action: "ls_update_session",
            security: ls_params.nonce,
            location: selectedSlug,
          },
          success: function (response) {
            if (response.success) {
              // STEP 2: Trigger WooCommerce update
              $("body").trigger("update_checkout");

              // Visual feedback
              setTimeout(function () {
                $("#ls-open-modal").text(selectedLabel);
                if ($("#ls-selected-display").length) {
                  $("#ls-selected-display").text("✓ " + selectedLabel);
                }
              }, 1500);
            } else {
              window.alert("Error updating location");
              $("#ls-open-modal").text("Try Again");
            }
          },
          error: function () {
            window.alert("Connection error");
            $("#ls-open-modal").text("Try Again");
          },
        });
      } else {
        window.alert("Please select a delivery area.");
      }
    });
  });
})(jQuery);
