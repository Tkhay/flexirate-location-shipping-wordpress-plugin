(function ($) {
  "use strict";

  $(document).ready(function () {
    // Add Row
    $(".ls_add_rule_button").on("click", function (e) {
      e.preventDefault();

      // Get the correct field name prefix from the first input found in the table
      const $firstInput = $("#ls_rules_table input").first();
      let fieldName = "woocommerce_locship_location_custom_rules"; // fallback

      if ($firstInput.length) {
        fieldName = $firstInput.attr("name").replace("[location][]", "");
      }

      // Clean HTML generation using single quotes
      let newRow = '<tr class="ls_rule">';
      newRow +=
        '<td><input type="text" name="' +
        fieldName +
        '[location][]" placeholder="e.g. Spintex, Sakumono" maxlength="100" required /></td>';
      newRow +=
        '<td><input type="number" name="' +
        fieldName +
        '[cost][]" placeholder="0.00" step="0.01" min="0" required /></td>';
      newRow += '<td><a href="#" class="ls-remove-rule">&times;</a></td>';
      newRow += "</tr>";

      $(".ls-rules-body").append(newRow);
    });

    // Remove Row
    $(document).on("click", ".ls-remove-rule", function (e) {
      e.preventDefault();
      if (window.confirm("Are you sure you want to remove this location?")) {
        $(this).closest("tr").remove();
      }
    });
  });
})(jQuery);

