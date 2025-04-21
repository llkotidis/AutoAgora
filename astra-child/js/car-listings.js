document.addEventListener("DOMContentLoaded", function () {
  // --- Access Localized Data ---
  // Ensure carListingsData is available (passed via wp_localize_script)
  if (typeof carListingsData === "undefined") {
    console.error("Car Listings script data not found.");
    return;
  }

  const ajaxurl = carListingsData.ajaxurl;
  const favoriteNonce = carListingsData.nonce;

  // --- Data from PHP (now localized) ---
  const originalCarListings = carListingsData.all_cars || [];
  const exteriorColorCounts = carListingsData.exterior_color_counts || {};
  const interiorColorCounts = carListingsData.interior_color_counts || {};
  const fuelTypeCounts = carListingsData.fuel_type_counts || {};
  const bodyTypeCounts = carListingsData.body_type_counts || {};
  const driveTypeCounts = carListingsData.drive_type_counts || {};
  const modelCounts = carListingsData.model_counts || {};
  const variantCounts = carListingsData.variant_counts || {};
  const yearCounts = carListingsData.year_counts || {};
  const priceCounts = carListingsData.price_counts || {};
  const kmCounts = carListingsData.km_counts || {};
  const engineCounts = carListingsData.engine_counts || {};
  const carData = carListingsData.variants_by_make_model || {};

  // --- DOM Elements ---
  const filtersButton = document.querySelector(".filters-button");
  const filtersPopup = document.getElementById("filtersPopup");
  const closeFilters = document.querySelector(".close-filters");
  const filterForm = document.querySelector(".filters-form");

  // Check if essential elements exist
  if (!filtersButton || !filtersPopup || !closeFilters || !filterForm) {
    // Don't throw error, just exit if filter elements aren't on the page
    // console.warn('Car Listings filter elements not found.');
    return;
  }

  // --- Popup functionality ---
  filtersButton.addEventListener("click", function () {
    filtersPopup.style.display = "block";
    document.body.style.overflow = "hidden";
  });

  closeFilters.addEventListener("click", function () {
    filtersPopup.style.display = "none";
    document.body.style.overflow = "";
  });

  filtersPopup.addEventListener("click", function (e) {
    if (e.target === filtersPopup) {
      filtersPopup.style.display = "none";
      document.body.style.overflow = "";
    }
  });

  // --- NEW: Helper function to get current filters from the form ---
  function getFiltersFromForm() {
    const formData = new FormData(filterForm);
    const filters = {};
    for (const [key, value] of formData.entries()) {
      if (value) {
        // Only consider non-empty values
        const cleanKey = key.replace(/\[\]$/, ""); // Remove [] for object keys
        if (key.endsWith("[]")) {
          if (!filters[cleanKey]) {
            filters[cleanKey] = [];
          }
          // Ensure unique values for checkboxes
          if (!filters[cleanKey].includes(value)) {
            filters[cleanKey].push(value);
          }
        } else {
          filters[key] = value; // Keep _min, _max keys as is
        }
      }
    }
    return filters;
  }

  // --- NEW: Central function to filter car listings ---
  function filterCars(listings, filters) {
    return listings.filter((car) => {
      let match = true;
      for (const key in filters) {
        const filterValue = filters[key];
        const carValue = car[key.replace(/_min$|_max$/, "")]; // Get corresponding car field (e.g., price for price_min)

        if (key.endsWith("_min")) {
          const min = parseFloat(filterValue);
          if (!isNaN(min) && parseFloat(carValue) < min) {
            match = false;
            break;
          }
        } else if (key.endsWith("_max")) {
          const max = parseFloat(filterValue);
          if (!isNaN(max) && parseFloat(carValue) > max) {
            match = false;
            break;
          }
        } else if (Array.isArray(filterValue)) {
          // Checkbox group (array of selected values)
          if (
            filterValue.length > 0 &&
            !filterValue.includes(String(carValue))
          ) {
            // Ensure comparison uses string if needed
            match = false;
            break;
          }
        } else {
          // Exact match (select dropdowns like make, model, location)
          if (String(carValue) !== String(filterValue)) {
            // Ensure comparison uses string
            match = false;
            break;
          }
        }
      }
      return match;
    });
  }

  // --- NEW: Master function to update all filter displays ---
  function updateAllFilterDisplays() {
    const activeFilters = getFiltersFromForm();

    // Update simple selects (Make, Location - others are dependent or range/checkbox)
    updateSelectDisplay("make", activeFilters);
    updateSelectDisplay("location", activeFilters); // Assuming location is simple

    // Update dependent selects (Model, Variant)
    updateModelDisplay(activeFilters);
    updateVariantDisplay(activeFilters);

    // Update checkbox groups
    [
      "fuel_type",
      "body_type",
      "drive_type",
      "exterior_color",
      "interior_color",
    ].forEach((fieldName) => {
      updateCheckboxGroupDisplay(fieldName, activeFilters);
    });

    // Update range selects
    ["price", "year", "mileage", "engine_capacity"].forEach((fieldName) => {
      updateRangeSelectDisplay(fieldName, activeFilters);
    });
  }

  // --- NEW: Update simple select dropdown display ---
  function updateSelectDisplay(fieldName, activeFilters) {
    const selectElement = filterForm.querySelector(
      `select[name="${fieldName}"]`
    );
    if (!selectElement) return;

    const filtersWithoutThis = { ...activeFilters };
    delete filtersWithoutThis[fieldName];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutThis
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      const value = car[fieldName];
      if (value) {
        // Ensure car has a value for this field
        valueCounts[value] = (valueCounts[value] || 0) + 1;
      }
    });

    updateOptionsInSelect(selectElement, valueCounts);
  }

  // --- NEW: Update Model select display ---
  function updateModelDisplay(activeFilters) {
    const selectElement = filterForm.querySelector(`select[name="model"]`);
    if (!selectElement) return;
    const currentMake = activeFilters["make"];

    // If no make is selected, clear and disable
    if (!currentMake) {
      selectElement.innerHTML = '<option value="">All Models</option>';
      selectElement.disabled = true;
      return;
    }

    // Filter based on everything EXCEPT model, but INCLUDING make
    const filtersWithoutModel = { ...activeFilters };
    delete filtersWithoutModel["model"];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutModel
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      // Count only models of the currently selected make
      if (car.make === currentMake && car.model) {
        valueCounts[car.model] = (valueCounts[car.model] || 0) + 1;
      }
    });

    updateOptionsInSelect(selectElement, valueCounts, "All Models");
    selectElement.disabled = false; // Enable since a make is selected
  }

  // --- NEW: Update Variant select display ---
  function updateVariantDisplay(activeFilters) {
    const selectElement = filterForm.querySelector(`select[name="variant"]`);
    if (!selectElement) return;
    const currentMake = activeFilters["make"];
    const currentModel = activeFilters["model"];

    // If no make or model is selected, clear and disable
    if (!currentMake || !currentModel) {
      selectElement.innerHTML = '<option value="">All Variants</option>';
      selectElement.disabled = true;
      return;
    }

    // Filter based on everything EXCEPT variant, but INCLUDING make and model
    const filtersWithoutVariant = { ...activeFilters };
    delete filtersWithoutVariant["variant"];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutVariant
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      // Count only variants of the currently selected make and model
      if (
        car.make === currentMake &&
        car.model === currentModel &&
        car.variant
      ) {
        valueCounts[car.variant] = (valueCounts[car.variant] || 0) + 1;
      }
    });

    updateOptionsInSelect(selectElement, valueCounts, "All Variants");
    selectElement.disabled = false; // Enable since make and model are selected
  }

  // --- NEW: Generic function to update options within any select element ---
  // (Replaces the complex updateSelectWithOptions)
  function updateOptionsInSelect(
    selectElement,
    valueCounts,
    placeholder = "Any"
  ) {
    const currentValue = selectElement.value; // Preserve current selection

    // Get all existing option values from the DOM before clearing (for base text)
    const existingOptions = {};
    selectElement.querySelectorAll("option").forEach((opt) => {
      if (opt.value !== "") {
        existingOptions[opt.value] = opt.textContent.split(" (")[0]; // Store base text
      }
    });

    selectElement.innerHTML = `<option value="">${placeholder}</option>`; // Clear and add placeholder

    // Get all unique values that have a count > 0 OR were originally in the select
    const allPossibleValues = new Set([
      ...Object.keys(valueCounts),
      ...Object.keys(existingOptions),
    ]);

    // Sort values alphabetically (optional)
    const sortedValues = [...allPossibleValues].sort((a, b) =>
      a.localeCompare(b)
    );

    sortedValues.forEach((value) => {
      const count = valueCounts[value] || 0;
      const baseText = existingOptions[value] || value; // Use original text or value itself
      const option = document.createElement("option");
      option.value = value;
      option.textContent = `${baseText} (${count})`;
      option.disabled = count === 0;
      selectElement.appendChild(option);
    });

    // Restore selection if possible
    if (selectElement.querySelector(`option[value="${currentValue}"]`)) {
      selectElement.value = currentValue;
    }

    // Update styles for disabled options (can be done via CSS)
    selectElement.querySelectorAll("option").forEach((opt) => {
      opt.style.opacity = opt.disabled ? "0.5" : "1";
      opt.style.cursor = opt.disabled ? "not-allowed" : "pointer";
    });
  }

  // --- NEW: Update checkbox group display ---
  function updateCheckboxGroupDisplay(fieldName, activeFilters) {
    const checkboxGroup = filterForm.querySelector(
      `div.checkbox-group[data-field="${fieldName}"]`
    );
    if (!checkboxGroup) return;

    const filtersWithoutThis = { ...activeFilters };
    // Handle array key format (e.g., 'fuel_type' corresponds to form name 'fuel_type[]')
    if (filtersWithoutThis[fieldName]) {
      delete filtersWithoutThis[fieldName];
    }

    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutThis
    );

    const valueCounts = {};
    carsMatchingOthers.forEach((car) => {
      const value = car[fieldName];
      if (value) {
        valueCounts[value] = (valueCounts[value] || 0) + 1;
      }
    });

    checkboxGroup.querySelectorAll(".checkbox-label").forEach((label) => {
      const checkbox = label.querySelector('input[type="checkbox"]');
      const value = checkbox.value;
      const count = valueCounts[value] || 0;
      const baseText =
        label.textContent.trim().split(" (")[0].replace(value, "").trim() ||
        value;
      // Find the text node to update safely
      let textNode = null;
      for (const node of label.childNodes) {
        if (
          node.nodeType === Node.TEXT_NODE &&
          node.textContent.trim() !== ""
        ) {
          textNode = node;
          break;
        }
      }
      if (textNode) {
        textNode.textContent = ` ${baseText} (${count})`;
      } else {
        // Fallback if text node wasn't found easily (might need adjustment)
        label.appendChild(document.createTextNode(` ${baseText} (${count})`));
      }

      checkbox.disabled = count === 0;
      label.style.opacity = checkbox.disabled ? "0.5" : "1";
      label.style.cursor = checkbox.disabled ? "not-allowed" : "pointer";
    });

    // Update the dropdown button label
    const button = checkboxGroup
      .closest(".checkbox-dropdown")
      .querySelector(".checkbox-dropdown-button");
    const selectedCount = checkboxGroup.querySelectorAll(
      'input[type="checkbox"]:checked:not(:disabled)'
    ).length;
    let labelText = "Select Options"; // Default
    const labelMap = {
      fuel_type: "Fuel Type",
      body_type: "Body Type",
      drive_type: "Drive Type",
      exterior_color: "Exterior Color",
      interior_color: "Interior Color",
    };
    labelText = labelMap[fieldName] || labelText;
    if (selectedCount > 0) {
      button.textContent = `${labelText}: ${selectedCount} selected`;
    } else {
      button.textContent = `Select ${labelText}`; // Placeholder text
    }
  }

  // --- NEW: Update range select display ---
  function updateRangeSelectDisplay(fieldName, activeFilters) {
    const minSelect = filterForm.querySelector(
      `select[name="${fieldName}_min"]`
    );
    const maxSelect = filterForm.querySelector(
      `select[name="${fieldName}_max"]`
    );
    if (!minSelect || !maxSelect) return;

    const minKey = fieldName + "_min";
    const maxKey = fieldName + "_max";

    const filtersWithoutThis = { ...activeFilters };
    delete filtersWithoutThis[minKey];
    delete filtersWithoutThis[maxKey];
    const carsMatchingOthers = filterCars(
      originalCarListings,
      filtersWithoutThis
    );

    // Update Min Select
    const minCounts = {};
    carsMatchingOthers.forEach((car) => {
      const carValue = parseFloat(car[fieldName]);
      if (isNaN(carValue)) return;
      // Also check against the current max filter if set
      const currentMax = activeFilters[maxKey]
        ? parseFloat(activeFilters[maxKey])
        : Infinity;
      if (carValue <= currentMax) {
        minSelect.querySelectorAll("option").forEach((opt) => {
          if (opt.value === "") return;
          const optValue = parseFloat(opt.value);
          if (carValue >= optValue) {
            // Car value is >= this min option
            minCounts[opt.value] = (minCounts[opt.value] || 0) + 1;
          }
        });
      }
    });
    updateOptionsInSelect(minSelect, minCounts, "Any");

    // Update Max Select
    const maxCounts = {};
    carsMatchingOthers.forEach((car) => {
      const carValue = parseFloat(car[fieldName]);
      if (isNaN(carValue)) return;
      // Also check against the current min filter if set
      const currentMin = activeFilters[minKey]
        ? parseFloat(activeFilters[minKey])
        : -Infinity;
      if (carValue >= currentMin) {
        maxSelect.querySelectorAll("option").forEach((opt) => {
          if (opt.value === "") return;
          const optValue = parseFloat(opt.value);
          if (carValue <= optValue) {
            // Car value is <= this max option
            maxCounts[opt.value] = (maxCounts[opt.value] || 0) + 1;
          }
        });
      }
    });
    updateOptionsInSelect(maxSelect, maxCounts, "Any");
  }

  // --- Event Listeners and Initialization ---
  document.querySelectorAll(".checkbox-group").forEach((group) => {
    const input = group.querySelector('input[type="checkbox"]');
    if (input && input.name) {
      const fieldName = input.name.replace(/\[\]$/, "");
      group.setAttribute("data-field", fieldName);
    }
  });

  function updateActiveFiltersDisplay() {
    const activeFiltersContainer = document.querySelector(
      ".active-filters-container"
    );
    if (!activeFiltersContainer) return; // Exit if bar isn't present

    const params = new URLSearchParams(window.location.search);
    activeFiltersContainer.innerHTML = "";
    const processedValues = new Set();

    params.forEach((value, key) => {
      if (value && key !== "paged") {
        const baseKey = key.replace(/\[\]$/, "");
        const label = formatFilterLabel(baseKey);

        if (key.endsWith("[]")) {
          const values = params.getAll(key);
          values.forEach((val) => {
            if (val) {
              const uniqueId = `${key}-${val}`;
              if (!processedValues.has(uniqueId)) {
                processedValues.add(uniqueId);
                const chip = createFilterChip(label, val, key, val);
                activeFiltersContainer.appendChild(chip);
              }
            }
          });
        } else if (!processedValues.has(key)) {
          processedValues.add(key);
          const chip = createFilterChip(label, value, key);
          activeFiltersContainer.appendChild(chip);
        }
      }
    });

    activeFiltersContainer
      .querySelectorAll(".remove-filter")
      .forEach((button) => {
        const newButton = button.cloneNode(true); // Clone to remove old listeners
        button.parentNode.replaceChild(newButton, button);

        newButton.addEventListener("click", function (e) {
          e.preventDefault(); // Prevent any default behavior

          const key = this.dataset.key;
          const valueToRemove = this.dataset.value; // data-value is set for multi-value items
          const baseKey = key.replace(/\\\[\\\]$/, ""); // Get base key e.g. 'fuel_type' from 'fuel_type[]'

          // Find the corresponding form element(s) and update them
          if (key.endsWith("[]")) {
            // Checkbox group
            const checkboxes = filterForm.querySelectorAll(
              `input[name="${key}"]`
            );
            checkboxes.forEach((cb) => {
              if (cb.value === valueToRemove) {
                cb.checked = false;
                // Manually trigger change event for consistency if needed elsewhere
                // cb.dispatchEvent(new Event('change', { bubbles: true }));
              }
            });
          } else if (key.endsWith("_min") || key.endsWith("_max")) {
            // Range select (min/max)
            const select = filterForm.querySelector(`select[name="${key}"]`);
            if (select) {
              select.value = ""; // Reset to 'Any'
              // select.dispatchEvent(new Event('change', { bubbles: true }));
            }
          } else {
            // Single select (make, model, location etc.)
            const select = filterForm.querySelector(`select[name="${key}"]`);
            if (select) {
              select.value = ""; // Reset to 'All'
              // select.dispatchEvent(new Event('change', { bubbles: true }));
            }
          }

          // Update filter counts and dependent dropdowns based on the change
          updateAllFilterDisplays();

          // Re-submit the form via AJAX
          submitFiltersWithAjax(1);
        }); // End of newButton click listener
      }); // End of forEach loop
  }

  function createFilterChip(label, value, key, dataValue = null) {
    const chip = document.createElement("div");
    chip.className = "active-filter";
    chip.innerHTML = `
             <span class="filter-label">${label}:</span>
             <span class="filter-value">${value}</span>
             <button class="remove-filter" data-key="${key}" ${
      dataValue ? `data-value="${dataValue}"` : ""
    }>×</button>
         `;
    return chip;
  }

  function formatFilterLabel(key) {
    const labelElement = filterForm.querySelector(
      `label[for="${key}"], label[for="${key}_min"]`
    );
    if (labelElement) {
      return labelElement.textContent.replace(":", "").trim();
    }
    const labels = {
      make: "Make",
      model: "Model",
      variant: "Variant",
      location: "Location",
      price_min: "Min Price",
      price_max: "Max Price",
      year_min: "Min Year",
      year_max: "Max Year",
      km_min: "Min KM",
      km_max: "Max KM",
      fuel_type: "Fuel Type",
      body_type: "Body Type",
      drive_type: "Drive Type",
      exterior_color: "Exterior Color",
      interior_color: "Interior Color",
      engine_min: "Min Engine",
      engine_max: "Max Engine",
    };
    return labels[key] || key;
  }

  updateActiveFiltersDisplay();

  filterForm.addEventListener("submit", function (e) {
    e.preventDefault();
    submitFiltersWithAjax(1); // Submit with page 1 on explicit form submission
  });

  function submitFiltersWithAjax(page = 1) {
    const formData = new FormData(filterForm);
    const filters = {};
    const params = new URLSearchParams(); // For URL update

    // Convert FormData to a plain object for easier handling in PHP
    // and build URL params for history.pushState
    for (const [key, value] of formData.entries()) {
      if (value) {
        const cleanKey = key.replace(/\[\]$/, ""); // Remove [] for object keys
        if (key.endsWith("[]")) {
          if (!filters[cleanKey]) {
            filters[cleanKey] = [];
          }
          filters[cleanKey].push(value);
          params.append(key, value); // Keep [] for URL params
        } else {
          filters[key] = value;
          params.set(key, value); // Set for URL params
        }
      }
    }

    // Add pagination to URL params if not page 1
    if (page > 1) {
      params.set("paged", page);
    } else {
      params.delete("paged"); // Clean URL for page 1
    }

    // Add AJAX action, nonce, and paged info
    const ajaxData = new FormData();
    ajaxData.append("action", "filter_car_listings");
    ajaxData.append("nonce", carListingsData.filter_nonce); // Use the nonce from localized data
    ajaxData.append("paged", page);
    // Append filters as individual fields
    for (const key in filters) {
      if (Array.isArray(filters[key])) {
        filters[key].forEach((val) =>
          ajaxData.append(`filters[${key}][]`, val)
        );
      } else {
        ajaxData.append(`filters[${key}]`, filters[key]);
      }
    }

    // --- Visual Feedback Start ---
    const listingsGrid = document.querySelector(".car-listings-grid");
    const paginationContainer = document.querySelector(
      ".car-listings-pagination"
    );
    const container = document.querySelector(".car-listings-container"); // Main container
    if (container) container.classList.add("is-loading"); // Add loading class
    if (listingsGrid) listingsGrid.style.opacity = "0.5";
    if (paginationContainer)
      paginationContainer.innerHTML =
        "<span class='loading-text'>Loading...</span>";
    // --- Visual Feedback End ---

    fetch(carListingsData.ajaxurl, {
      method: "POST",
      body: ajaxData,
      credentials: "same-origin",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (container) container.classList.remove("is-loading");
        if (data.success) {
          // Update listings grid
          if (listingsGrid) {
            listingsGrid.innerHTML = data.data.listings_html;
            listingsGrid.style.opacity = "1";
            // --- Re-initialize JS components within the new HTML ---
            reinitializeCarousels();
            reinitializeFavoriteButtons();
            // ---
          }

          // Update pagination
          if (paginationContainer) {
            paginationContainer.innerHTML = data.data.pagination_html;
          }

          // Update URL using History API - only add question mark if there are parameters
          const paramsString = params.toString();
          const newUrl = paramsString 
            ? window.location.pathname + "?" + paramsString 
            : window.location.pathname;
          
          // Only push state if URL is different to avoid duplicate entries on simple pagination clicks
          if (window.location.href !== newUrl) {
            history.pushState({ path: newUrl, page: page }, "", newUrl);
          }

          // Update active filter chips display (if applicable)
          if (typeof updateActiveFiltersDisplay === "function") {
            updateActiveFiltersDisplay();
          }

          // Close the filter popup on successful AJAX update
          if (filtersPopup) {
            // filtersPopup should be available from the top scope
            filtersPopup.style.display = "none";
            document.body.style.overflow = ""; // Restore body scroll
          }
        } else {
          console.error("AJAX Error:", data);
          if (listingsGrid) listingsGrid.style.opacity = "1";
          if (paginationContainer)
            paginationContainer.innerHTML =
              "<span class='error-text'>Error loading results.</span>";

          // Update active filter chips display (if applicable)
          if (typeof updateActiveFiltersDisplay === "function") {
            updateActiveFiltersDisplay();
          }
        }
      })
      .catch((error) => {
        console.error("Fetch Error:", error);
        if (container) container.classList.remove("is-loading");
        if (listingsGrid) listingsGrid.style.opacity = "1";
        if (paginationContainer)
          paginationContainer.innerHTML =
            "<span class='error-text'>Error loading results.</span>";
      });
  }

  // --- Handle Pagination Clicks ---
  const paginationContainer = document.querySelector(
    ".car-listings-pagination"
  );
  if (paginationContainer) {
    // Use event delegation
    paginationContainer.addEventListener("click", function (e) {
      const pageLink = e.target.closest("a.page-numbers"); // Find the closest anchor
      if (pageLink && !pageLink.classList.contains("current")) {
        e.preventDefault();
        
        // Get the current page from the URL with proper validation
        const currentUrl = new URL(window.location.href);
        let currentPage = parseInt(currentUrl.searchParams.get("paged"), 10);
        // Ensure currentPage is a valid number and at least 1
        if (isNaN(currentPage) || currentPage < 1) {
          currentPage = 1;
        }
        
        // Determine the target page
        let targetPage = currentPage;
        
        // Check if it's a direct page number link
        const href = pageLink.getAttribute("href");
        const hrefUrl = new URL(href, window.location.origin);
        const pagedParam = hrefUrl.searchParams.get("paged");
        
        if (pagedParam) {
          // Direct page number link
          targetPage = parseInt(pagedParam, 10);
          // Validate the target page
          if (isNaN(targetPage) || targetPage < 1) {
            targetPage = 1;
          }
        } else {
          // Check if it's a next/prev link
          if (href.includes("next")) {
            targetPage = currentPage + 1;
          } else if (href.includes("prev")) {
            targetPage = Math.max(1, currentPage - 1);
          }
        }
        
        // Submit the form with the correct page number
        submitFiltersWithAjax(targetPage);
        
        // Optional: Scroll to top of listings
        const listingsContainer = document.querySelector(
          ".car-listings-container"
        );
        if (listingsContainer) {
          listingsContainer.scrollIntoView({ behavior: "smooth" });
        }
      }
    });
  }

  // --- Handle Browser Back/Forward ---
  window.addEventListener("popstate", function (event) {
    // We could re-submit the form based on the URL state,
    // but simply reloading might be easier and more reliable
    // unless complex state management is implemented.
    // For now, let's reload to reflect the URL state.
    window.location.reload();
  });

  // --- Helper functions to re-initialize JS components ---
  function reinitializeCarousels() {
    const carousels = document.querySelectorAll(".car-listing-image-carousel");
    
    carousels.forEach((carousel, index) => {
      const images = carousel.querySelectorAll(".car-listing-image");
      const prevBtn = carousel.querySelector(".carousel-nav.prev");
      const nextBtn = carousel.querySelector(".carousel-nav.next");
      const seeAllImagesBtn = carousel.querySelector(".see-all-images");
      let currentIndex = 0;

      // If there's only one image, hide all navigation elements
      if (images.length <= 1) {
        if (prevBtn) prevBtn.style.display = "none";
        if (nextBtn) nextBtn.style.display = "none";
        if (seeAllImagesBtn) seeAllImagesBtn.style.display = "none";
        return;
      }

      // Function to update image visibility and navigation buttons
      const updateCarousel = () => {
        // Update image visibility
        images.forEach((img, i) => {
          img.classList.toggle("active", i === currentIndex);
        });

        // Update navigation buttons - explicitly set display based on current index
        if (prevBtn) {
          prevBtn.style.display = currentIndex === 0 ? "none" : "flex";
        }
        
        if (nextBtn) {
          nextBtn.style.display = currentIndex === images.length - 1 ? "none" : "flex";
        }
        
        if (seeAllImagesBtn) {
          seeAllImagesBtn.style.display = currentIndex === images.length - 1 ? "block" : "none";
        }
      };

      // Set initial state
      updateCarousel();

      // Add event listeners to navigation buttons
      if (prevBtn) {
        prevBtn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          if (currentIndex > 0) {
            currentIndex--;
            updateCarousel();
          }
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          if (currentIndex < images.length - 1) {
            currentIndex++;
            updateCarousel();
          }
        });
      }
    });
  }

  function reinitializeFavoriteButtons() {
    const buttons = document.querySelectorAll(".favorite-btn");
    
    buttons.forEach((button, index) => {
      // --- Re-attach listener using cloning ---
      const newButton = button.cloneNode(true);
      button.parentNode.replaceChild(newButton, button);

      newButton.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        const carId = this.getAttribute("data-car-id");
        const isActive = this.classList.contains("active");
        const heartIcon = this.querySelector("i");

        // Toggle UI immediately for responsiveness
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
        formData.append("nonce", carListingsData.nonce); // Original favorite nonce

        fetch(ajaxurl, {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        })
          .then((response) => response.json())
          .then((data) => {
            if (!data.success) {
              // Revert UI on failure
              this.classList.toggle("active");
              if (isActive) {
                heartIcon.classList.remove("far");
                heartIcon.classList.add("fas");
              } else {
                heartIcon.classList.remove("fas");
                heartIcon.classList.add("far");
              }
              alert("Failed to update favorites. Please try again.");
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            // Revert UI on failure
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
      // --- End Re-attach listener ---
    });
  }

  const makeSelect = document.getElementById("make");
  const modelSelect = document.getElementById("model");
  const variantSelect = document.getElementById("variant");

  function populateModels() {
    if (!makeSelect || !modelSelect || !variantSelect) return;
    const make = makeSelect.value;
    const currentModel = new URLSearchParams(window.location.search).get(
      "model"
    );
    modelSelect.innerHTML = '<option value="">All Models</option>';
    variantSelect.innerHTML = '<option value="">All Variants</option>';
    variantSelect.disabled = true;

    if (make && carData[make]) {
      modelSelect.disabled = false;
      Object.keys(carData[make])
        .sort()
        .forEach((model) => {
          const option = document.createElement("option");
          option.value = model;
          const count = modelCounts[make]?.[model] || 0;
          option.textContent = `${model} (${count})`;
          if (model === currentModel) {
            option.selected = true;
          }
          modelSelect.appendChild(option);
        });
      if (currentModel) {
        populateVariants();
      }
    } else {
      modelSelect.disabled = true;
    }
  }

  function populateVariants() {
    if (!makeSelect || !modelSelect || !variantSelect) return;
    const make = makeSelect.value;
    const model = modelSelect.value;
    const currentVariant = new URLSearchParams(window.location.search).get(
      "variant"
    );
    variantSelect.innerHTML = '<option value="">All Variants</option>';

    if (make && model && carData[make]?.[model]) {
      variantSelect.disabled = false;
      carData[make][model].sort().forEach((variant) => {
        const option = document.createElement("option");
        option.value = variant;
        const count = variantCounts[make]?.[model]?.[variant] || 0;
        option.textContent = `${variant} (${count})`;
        if (variant === currentVariant) {
          option.selected = true;
        }
        variantSelect.appendChild(option);
      });
    } else {
      variantSelect.disabled = true;
    }
  }

  if (makeSelect) {
    makeSelect.addEventListener("change", () => {
      populateModels();
      updateAllFilterDisplays();
    });
  }

  if (modelSelect) {
    modelSelect.addEventListener("change", () => {
      populateVariants();
      updateAllFilterDisplays();
    });
  }

  if (variantSelect) {
    variantSelect.addEventListener("change", () => {
      updateAllFilterDisplays();
    });
  }

  filterForm
    .querySelectorAll(
      'select:not(#make):not(#model):not(#variant), input[type="checkbox"]'
    )
    .forEach((input) => {
      input.addEventListener("change", updateAllFilterDisplays);
    });

  document.querySelectorAll(".checkbox-dropdown").forEach((dropdown) => {
    const button = dropdown.querySelector(".checkbox-dropdown-button");
    const group = dropdown.querySelector(".checkbox-group");

    if (button && group) {
      button.addEventListener("click", function (e) {
        e.stopPropagation();

        // Close all other active dropdowns first
        document
          .querySelectorAll(".checkbox-dropdown.active")
          .forEach((otherDropdown) => {
            if (otherDropdown !== dropdown) {
              // Don't close the one being clicked
              otherDropdown.classList.remove("active");
              otherDropdown.querySelector(".checkbox-group").style.display =
                "none";
            }
          });

        // Now toggle the current one
        const isActive = dropdown.classList.toggle("active");
        group.style.display = isActive ? "block" : "none";
      });
    }
  });

  // Close dropdowns when clicking outside
  document.addEventListener("click", function (e) {
    document
      .querySelectorAll(".checkbox-dropdown.active")
      .forEach((dropdown) => {
        const button = dropdown.querySelector(".checkbox-dropdown-button");
        const group = dropdown.querySelector(".checkbox-group");
        if (
          button &&
          group &&
          !button.contains(e.target) &&
          !group.contains(e.target)
        ) {
          dropdown.classList.remove("active");
          group.style.display = "none";
        }
      });
  });

  // --- Initial Page Load ---
  const initialParams = new URLSearchParams(window.location.search);
  const initialMake = initialParams.get("make");
  const initialModel = initialParams.get("model");
  const initialVariant = initialParams.get("variant");

  if (makeSelect && initialMake) {
    if (
      Array.from(makeSelect.options).some((opt) => opt.value === initialMake)
    ) {
      makeSelect.value = initialMake;
      populateModels();
      if (modelSelect && initialModel) {
        if (
          Array.from(modelSelect.options).some(
            (opt) => opt.value === initialModel
          )
        ) {
          modelSelect.value = initialModel;
          populateVariants();
          if (variantSelect && initialVariant) {
            if (
              Array.from(variantSelect.options).some(
                (opt) => opt.value === initialVariant
              )
            ) {
              variantSelect.value = initialVariant;
            }
          }
        }
      }
    }
  }

  document
    .querySelectorAll('.checkbox-group input[type="checkbox"]')
    .forEach((cb) => {
      const key = cb.name;
      const value = cb.value;
      if (initialParams.getAll(key).includes(value)) {
        cb.checked = true;
      }
    });

  ["price", "year", "mileage", "engine_capacity", "location"].forEach(
    (field) => {
      const fieldKey = field.includes("_") ? field : field; // location doesn't have _min/_max
      const minParam = field + "_min";
      const maxParam = field + "_max";

      if (initialParams.has(fieldKey)) {
        const element = filterForm.querySelector(`select[name="${fieldKey}"]`);
        if (element) element.value = initialParams.get(fieldKey);
      } else {
        if (initialParams.has(minParam)) {
          const element = filterForm.querySelector(
            `select[name="${minParam}"]`
          );
          if (element) element.value = initialParams.get(minParam);
        }
        if (initialParams.has(maxParam)) {
          const element = filterForm.querySelector(
            `select[name="${maxParam}"]`
          );
          if (element) element.value = initialParams.get(maxParam);
        }
      }
    }
  );

  updateAllFilterDisplays();
  
  // Initialize carousels and favorite buttons on initial page load
  reinitializeCarousels();
  reinitializeFavoriteButtons();
});
