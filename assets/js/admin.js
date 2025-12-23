(function ($) {
  "use strict";

  $(document).ready(function () {
    // Add Row
    $(".locsh_for_woocom_add_rule_button").on("click", function (e) {
      e.preventDefault();

      // Get the correct field name prefix from the first input found in the table
      const $firstInput = $("#locsh_for_woocom_rules_table input").first();
      let fieldName = "woocommerce_locship_location_custom_rules"; // fallback

      if ($firstInput.length) {
        fieldName = $firstInput.attr("name").replace("[location][]", "");
      }

      // Clean HTML generation using single quotes
      let newRow = '<tr class="locsh_for_woocom_rule">';
      newRow +=
        '<td><input type="text" name="' +
        fieldName +
        '[location][]" placeholder="e.g. Spintex, Sakumono" maxlength="100" required /></td>';
      newRow +=
        '<td><input type="number" name="' +
        fieldName +
        '[cost][]" placeholder="0.00" step="0.01" min="0" required /></td>';
      newRow += '<td><a href="#" class="locsh-for-woocom-remove-rule">&times;</a></td>';
      newRow += "</tr>";

      $(".locsh-for-woocom-rules-body").append(newRow);
    });

    // Remove Row
    $(document).on("click", ".locsh-for-woocom-remove-rule", function (e) {
      e.preventDefault();
      if (window.confirm("Are you sure you want to remove this location?")) {
        $(this).closest("tr").remove();
      }
    });
  });
})(jQuery);

