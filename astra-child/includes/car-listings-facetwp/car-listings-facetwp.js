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
            this.afterAjaxUpdate();
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

    reinitializeCarousels() {
      this.initializeCarousels();
    }

    initializeCarousels() {
      document
        .querySelectorAll(".car-listing-image-carousel")
        .forEach((carousel) => {
          const images = carousel.querySelectorAll(".car-listing-image");
          const prevBtn = carousel.querySelector(".carousel-nav.prev");
          const nextBtn = carousel.querySelector(".carousel-nav.next");
          const seeAllImagesBtn = carousel.querySelector(".see-all-images");
          let currentIndex = 0;
          let counter = carousel.querySelector(".image-counter");
          if (!counter) {
            counter = document.createElement("div");
            counter.className = "image-counter";
            carousel.appendChild(counter);
          }
          counter.textContent =
            images.length > 0 ? `1/${images.length}` : "0/0";
          const updateImages = () => {
            if (images.length === 0) {
              prevBtn.style.display = "none";
              nextBtn.style.display = "none";
              if (seeAllImagesBtn) seeAllImagesBtn.style.display = "none";
              counter.textContent = "0/0";
              return;
            }
            images.forEach((img, index) => {
              img.classList.toggle("active", index === currentIndex);
            });
            prevBtn.style.display = currentIndex === 0 ? "none" : "flex";
            nextBtn.style.display =
              currentIndex === images.length - 1 ? "none" : "flex";
            if (seeAllImagesBtn) {
              seeAllImagesBtn.style.display =
                currentIndex === images.length - 1 ? "block" : "none";
            }
            counter.textContent = `${currentIndex + 1}/${images.length}`;
          };
          updateImages();
          prevBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (currentIndex > 0) {
              currentIndex--;
              updateImages();
            }
          });
          nextBtn.addEventListener("click", (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (currentIndex < images.length - 1) {
              currentIndex++;
              updateImages();
            }
          });
        });
    }

    reinitializeFavoriteButtons() {
      const buttons = document.querySelectorAll(".favorite-btn");
      buttons.forEach((button) => {
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        newButton.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          if (
            typeof carListingsFacetWP === "undefined" ||
            typeof carListingsFacetWP.ajaxurl === "undefined" ||
            typeof carListingsFacetWP.nonce === "undefined"
          ) {
            alert("Please log in to add favorites.");
            return;
          }
          const carId = this.getAttribute("data-car-id");
          const isActive = this.classList.contains("active");
          const heartIcon = this.querySelector("i");
          this.classList.toggle("active");
          if (isActive) {
            heartIcon.classList.remove("fas");
            heartIcon.classList.add("far");
          } else {
            heartIcon.classList.remove("far");
            heartIcon.classList.add("fas");
          }
          const formData = new FormData();
          formData.append("action", "toggle_favorite_car");
          formData.append("car_id", carId);
          formData.append("is_favorite", !isActive ? "1" : "0");
          formData.append("nonce", carListingsFacetWP.nonce);
          fetch(carListingsFacetWP.ajaxurl, {
            method: "POST",
            body: formData,
            credentials: "same-origin",
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error("Network response was not ok.");
              }
              return response.json();
            })
            .then((data) => {
              if (!data.success) {
                this.classList.toggle("active");
                if (isActive) {
                  heartIcon.classList.remove("far");
                  heartIcon.classList.add("fas");
                } else {
                  heartIcon.classList.remove("fas");
                  heartIcon.classList.add("far");
                }
                console.error("Favorite toggle failed:", data);
                alert("Failed to update favorites. Please try again.");
              }
            })
            .catch((error) => {
              console.error("Error:", error);
              this.classList.toggle("active");
              if (isActive) {
                heartIcon.classList.remove("far");
                heartIcon.classList.add("fas");
              } else {
                heartIcon.classList.remove("fas");
                heartIcon.classList.add("far");
              }
              alert("Failed to update favorites. Please try again.");
            });
        });
      });
    }

    afterAjaxUpdate() {
      this.reinitializeCarousels();
      this.reinitializeFavoriteButtons();
    }
  }

  // Initialize when document is ready
  $(document).ready(() => {
    new CarListingsFacetWP();
  });
})(jQuery);
