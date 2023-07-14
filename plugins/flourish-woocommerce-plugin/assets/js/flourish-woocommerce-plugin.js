(function ($) {
  $(document).ready(function () {
    $('#flourish-woocommerce-plugin-filter-brands').on('click', function() {
      $('#flourish-woocommerce-plugin-brand-selection').toggle(this.checked);
    });
  });
})(jQuery);