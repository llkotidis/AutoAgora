(function ($) {
  "use strict";

  class CarListingsFacetWP {
    constructor() {
      this.container = $(".car-listings-facetwp-container");
      if (!this.container.length) return;

      this.grid = this.container.find(".car-listings-grid");
      this.loading = this.container.find(".car-listings-loading");
      this.currentUrl = window.location.href;
      this.isLoading = false;

      this.init();
    }

    init() {
      // Listen for FacetWP events
      $(document).on("facetwp-loaded", () => this.handleFacetWPUpdate());
      $(document).on("facetwp-refresh", () => this.handleFacetWPUpdate());

      // Initial load
      this.loadListings();
    }

    handleFacetWPUpdate() {
      const newUrl = window.location.href;
      if (newUrl !== this.currentUrl) {
        this.currentUrl = newUrl;
        this.loadListings();
      }
    }

    loadListings() {
      if (this.isLoading) return;
      this.isLoading = true;
      this.showLoading();

      $.ajax({
        url: carListingsFacetWP.ajaxurl,
        type: "POST",
        data: {
          action: "load_car_listings",
          nonce: carListingsFacetWP.nonce,
          url: this.currentUrl,
        },
        success: (response) => {
          if (response.success) {
            this.grid.html(response.data.html);
            this.initializeLazyLoading();
          }
        },
        error: (xhr, status, error) => {
          console.error("Error loading car listings:", error);
        },
        complete: () => {
          this.hideLoading();
          this.isLoading = false;
        },
      });
    }

    showLoading() {
      this.loading.show();
      this.grid.addClass("loading");
    }

    hideLoading() {
      this.loading.hide();
      this.grid.removeClass("loading");
    }

    initializeLazyLoading() {
      // Initialize lazy loading for images if needed
      if (typeof LazyLoad !== "undefined") {
        new LazyLoad({
          elements_selector: ".car-listing-image img",
        });
      }
    }
  }

  // Initialize when document is ready
  $(document).ready(() => {
    new CarListingsFacetWP();
  });
})(jQuery);
