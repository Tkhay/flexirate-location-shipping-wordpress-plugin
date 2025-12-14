(function ($) {
  "use strict";

  $(document).ready(function () {
    // Add Row
    $(".wcsre_add_rule_button").on("click", function (e) {
      e.preventDefault();

      // Get the correct field name prefix from the first input found in the table
      const $firstInput = $("#wcsre_rules_table input").first();
      let fieldName = "woocommerce_wc_rate_engine_custom_rules"; // fallback

      if ($firstInput.length) {
        fieldName = $firstInput.attr("name").replace("[location][]", "");
      }

      // Clean HTML generation using single quotes
      let newRow = '<tr class="wcsre_rule">';
      newRow +=
        '<td><input type="text" name="' +
        fieldName +
        '[location][]" placeholder="e.g. Spintex, Sakumono" /></td>';
      newRow +=
        '<td><input type="number" name="' +
        fieldName +
        '[cost][]" placeholder="0.00" step="0.01" /></td>';
      newRow += '<td><a href="#" class="wcsre-remove-rule">&times;</a></td>';
      newRow += "</tr>";

      $(".wcsre-rules-body").append(newRow);
    });

    // Remove Row
    $(document).on("click", ".wcsre-remove-rule", function (e) {
      e.preventDefault();
      if (window.confirm("Are you sure you want to remove this location?")) {
        $(this).closest("tr").remove();
      }
    });
  });
})(jQuery);
