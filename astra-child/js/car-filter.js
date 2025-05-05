(function ($) {
  // Start jQuery no-conflict wrapper
  document.addEventListener("DOMContentLoaded", function () {
    console.log("[CarFilter] DOMContentLoaded event fired.");

    // Note: The 'carFilterData' object will be provided by wp_localize_script in PHP

    if (typeof carFilterData === "undefined") {
      console.error(
        "Car Filter Error: Localized data (carFilterData) not found."
      );
      return; // Stop execution if data is missing
    }

    const filterData = carFilterData; // Use the localized data
    const context = filterData.context;
    const ajaxUrl = filterData.ajaxUrl;
    const updateNonce = filterData.updateNonce;
    const updateAction = filterData.updateAction;
    const makeModelVariantStructure = filterData.makeModelVariantStructure;
    const allChoices = filterData.choices; // All possible static choices (ACF)

    console.log("[CarFilter] Initializing filters for context:", context);

    const form = document.getElementById("car-filter-form-" + context);
    // Ensure form exists before proceeding
    if (!form) {
      console.error(
        "[CarFilter] Form element not found with ID:",
        "car-filter-form-" + context
      );
      return;
    }
    console.log("[CarFilter] Form element found:", form);

    const container = form.closest(".car-filter-form-container");
    const filterSelects = form.querySelectorAll("select[data-filter-key]");
    const multiSelectFilters = form.querySelectorAll(".multi-select-filter"); // Get multi-selects
    console.log(
      "[CarFilter] Found multiSelectFilters elements:",
      multiSelectFilters.length,
      multiSelectFilters
    );
    const allFilterElements = form.querySelectorAll("[data-filter-key]"); // All elements with filter keys
    const resetButton = form.querySelector(".filter-reset-button");

    // Store initial state for multi-selects to detect changes
    let multiSelectInitialValues = {};

    // --- Helper: JS number_format (basic version) ---
    function number_format(number, decimals = 0) {
      number = parseFloat(number);
      if (isNaN(number)) {
        return "";
      }
      return number.toFixed(decimals);
    }

    // --- Helper: Get all current filter values ---
    function getCurrentFilters() {
      const filters = {};
      allFilterElements.forEach((element) => {
        const key = element.getAttribute("data-filter-key");
        let value = null;

        if (element.matches("select")) {
          value = element.value;
        } else if (element.matches(".multi-select-filter")) {
          const hiddenInput = element.querySelector(".multi-select-value");
          value = hiddenInput ? hiddenInput.value : ""; // Get comma-separated string
        }

        if (key && value) {
          filters[key] = value;
        }
      });
      return filters;
    }

    // --- Helper: Update Display Text for Multi-Select ---
    function updateMultiSelectDisplay(multiSelectElement) {
      const displaySpan = multiSelectElement.querySelector(
        ".multi-select-display > span:first-child"
      );
      const checkboxes = multiSelectElement.querySelectorAll(
        '.multi-select-popup input[type="checkbox"]:checked'
      );
      const hiddenInput = multiSelectElement.querySelector(
        ".multi-select-value"
      );
      // Get default text from data attribute
      const defaultText =
        multiSelectElement
          .querySelector(".multi-select-display")
          .getAttribute("data-default-text") || "Select Options";

      const selectedLabels = [];
      const selectedValues = [];
      checkboxes.forEach((cb) => {
        selectedValues.push(cb.value);
        // Use data-label attribute stored during PHP generation
        const label = cb.getAttribute("data-label");
        if (label) selectedLabels.push(label);
        else selectedLabels.push(cb.value); // Fallback to value
      });

      if (selectedLabels.length === 0) {
        displaySpan.textContent = defaultText; // Show default text like "All Fuel Types"
      } else if (selectedLabels.length <= 2) {
        // Show names if 1 or 2 selected
        displaySpan.textContent = selectedLabels.join(", ");
      } else {
        // Show count if more than 2
        displaySpan.textContent = selectedLabels.length + " selected";
      }

      hiddenInput.value = selectedValues.join(","); // Update hidden input
    }

    // --- Helper: Update Options in a Standard Select Dropdown ---
    function updateSelectOptions(
      selectElement,
      choices,
      counts,
      defaultOptionText,
      keepExistingValue = true
    ) {
      const currentVal = selectElement.value;
      const filterKey = selectElement.getAttribute("data-filter-key");

      selectElement.innerHTML = ""; // Clear existing options

      // Add the default "All/Any..." option
      const defaultOption = document.createElement("option");
      defaultOption.value = "";
      defaultOption.textContent = defaultOptionText;
      selectElement.appendChild(defaultOption);

      // Add the actual filter options
      Object.entries(choices)
        .sort(([, a], [, b]) => a.localeCompare(b))
        .forEach(([value, label]) => {
          const count = counts[value] || 0;
          const option = document.createElement("option");
          option.value = value;
          option.textContent = label + " (" + count + ")";
          option.disabled = count === 0;
          selectElement.appendChild(option);
        });

      // Restore previous selection if possible and desired
      if (
        keepExistingValue &&
        currentVal &&
        selectElement.querySelector(
          `option[value="${currentVal}"]:not([disabled])`
        )
      ) {
        selectElement.value = currentVal;
      } else {
        selectElement.value = ""; // Reset if previous value is no longer valid/available
      }

      // Special handling for model/variant enablement
      if (filterKey === "model") {
        const makeSelect = form.querySelector("#filter-make-" + context);
        selectElement.disabled = !makeSelect || !makeSelect.value; // Check if makeSelect exists
      }
      if (filterKey === "variant") {
        const modelSelect = form.querySelector("#filter-model-" + context);
        selectElement.disabled = !modelSelect || !modelSelect.value; // Check if modelSelect exists
      }
    }

    // --- Main Filter Update Function (AJAX Call) ---
    let ajaxRequestPending = false; // Prevent simultaneous requests
    function handleFilterChange(triggeredByMultiSelectKey = null) {
      // Pass key if triggered by multi-select close
      if (ajaxRequestPending) return; // Don't stack requests
      ajaxRequestPending = true;

      // If triggered by multi-select closing, check if value actually changed
      if (triggeredByMultiSelectKey) {
        const multiSelectElement = form.querySelector(
          `.multi-select-filter[data-filter-key="${triggeredByMultiSelectKey}"]`
        );
        if (multiSelectElement) {
          // Check if element exists
          const hiddenInput = multiSelectElement.querySelector(
            ".multi-select-value"
          );
          if (
            hiddenInput &&
            hiddenInput.value ===
              multiSelectInitialValues[triggeredByMultiSelectKey]
          ) {
            ajaxRequestPending = false;
            return; // Value didn't change, no need for AJAX
          }
        } else {
          ajaxRequestPending = false; // Element not found, exit
          return;
        }
      }

      const currentFilters = getCurrentFilters();

      const formData = new FormData();
      formData.append("action", updateAction);
      formData.append("nonce", updateNonce);
      for (const key in currentFilters) {
        formData.append(`filters[${key}]`, currentFilters[key]);
      }

      fetch(ajaxUrl, { method: "POST", body: formData })
        .then((response) => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then((result) => {
          if (result.success && result.data) {
            const updatedCounts = result.data;

            allFilterElements.forEach((element) => {
              const filterKey = element.getAttribute("data-filter-key");

              // --- Update Standard Select ---
              if (element.matches("select")) {
                // Skip range inputs here, handle them separately below
                if (filterKey.endsWith("_min") || filterKey.endsWith("_max")) {
                  return;
                }

                let choicesForThisSelect = {};
                let defaultText = "All"; // Generic default

                switch (filterKey) {
                  case "make":
                    const makeOptions = element.querySelectorAll("option");
                    const countsForMake = updatedCounts.make || {}; // Ensure counts object exists
                    makeOptions.forEach((opt) => {
                      if (opt.value) {
                        const count = countsForMake[opt.value] || 0;
                        opt.textContent = opt.value + " (" + count + ")";
                        opt.disabled = count === 0;
                      }
                    });
                    const currentMake = element.value;
                    // Check if the currently selected value is still valid (exists and not disabled)
                    if (
                      currentMake &&
                      element.querySelector(
                        `option[value="${currentMake}"]:not([disabled])`
                      )
                    ) {
                      element.value = currentMake;
                    } else if (currentMake) {
                      // If current selection is no longer valid, reset
                      element.value = "";
                    } // Otherwise, leave it as is (might be '')
                    break; // Skip generic update
                  case "model":
                    const makeSelect = form.querySelector(
                      "#filter-make-" + context
                    );
                    const selectedMake = makeSelect ? makeSelect.value : ""; // Check if makeSelect exists
                    if (
                      selectedMake &&
                      makeModelVariantStructure[selectedMake]
                    ) {
                      choicesForThisSelect = Object.keys(
                        makeModelVariantStructure[selectedMake]
                      ).reduce((obj, key) => {
                        obj[key] = key;
                        return obj;
                      }, {});
                      defaultText = "All Models";
                    } else {
                      choicesForThisSelect = {};
                      defaultText = "Select Make First";
                    }
                    const countsForModel = updatedCounts[filterKey] || {};
                    updateSelectOptions(
                      element,
                      choicesForThisSelect,
                      countsForModel,
                      defaultText
                    );
                    break;
                  case "variant":
                    const selMake = form.querySelector(
                      "#filter-make-" + context
                    )?.value; // Optional chaining
                    const selModel = form.querySelector(
                      "#filter-model-" + context
                    )?.value; // Optional chaining
                    if (
                      selMake &&
                      selModel &&
                      makeModelVariantStructure[selMake] &&
                      makeModelVariantStructure[selMake][selModel]
                    ) {
                      choicesForThisSelect = makeModelVariantStructure[selMake][
                        selModel
                      ].reduce((obj, key) => {
                        obj[key] = key;
                        return obj;
                      }, {});
                      defaultText = "All Variants";
                    } else {
                      choicesForThisSelect = {};
                      defaultText = "Select Model First";
                    }
                    const countsForVariant = updatedCounts[filterKey] || {};
                    updateSelectOptions(
                      element,
                      choicesForThisSelect,
                      countsForVariant,
                      defaultText
                    );
                    break;
                  // Add default case for other standard selects if they exist
                  default:
                    // Skip updating location, as its state is static based on initial render
                    if (filterKey === "location") break;

                    // Handle generic single-select dropdowns like 'location'
                    const genericChoices = allChoices[filterKey] || {};
                    const genericCounts = updatedCounts[filterKey] || {};
                    let genericDefaultText = "All"; // Replace with specific if needed
                    if (filterKey === "location")
                      genericDefaultText = "All Locations";
                    updateSelectOptions(
                      element,
                      genericChoices,
                      genericCounts,
                      genericDefaultText
                    );
                    break;
                }
                // --- Update Multi Select ---
              } else if (element.matches(".multi-select-filter")) {
                const countsForThisMultiSelect = updatedCounts[filterKey] || {};
                const checkboxes = element.querySelectorAll(
                  '.multi-select-popup input[type="checkbox"]'
                );

                checkboxes.forEach((cb) => {
                  const value = cb.value;
                  const count = countsForThisMultiSelect[value] || 0;
                  const countSpan = cb
                    .closest("label")
                    ?.querySelector(".option-count");
                  const listItem = cb.closest("li"); // Get the parent li

                  if (countSpan) {
                    countSpan.textContent = count;
                  }
                  // Set disabled state based on count
                  cb.disabled = count === 0;
                  // Add/Remove class for visual styling
                  if (listItem) {
                    if (count === 0) {
                      listItem.classList.add("disabled-option");
                    } else {
                      listItem.classList.remove("disabled-option");
                    }
                  }
                });
              }
            });

            const engineMinSelect = form.querySelector(
              'select[data-filter-key="engine_min"]'
            );
            const engineMaxSelect = form.querySelector(
              'select[data-filter-key="engine_max"]'
            );
            const engineMinCumulativeCounts =
              updatedCounts.engine_min_cumulative_counts || {};
            const engineMaxCumulativeCounts =
              updatedCounts.engine_max_cumulative_counts || {};

            console.log("Min Cumulative Counts:", engineMinCumulativeCounts); // Debug
            console.log("Max Cumulative Counts:", engineMaxCumulativeCounts); // Debug

            function updateEngineOptions(selectElement, isMinSelect, suffix) {
              if (!selectElement) return;
              const cumulativeCounts = isMinSelect
                ? engineMinCumulativeCounts
                : engineMaxCumulativeCounts;
              const selectType = isMinSelect ? "Min" : "Max"; // For logging
              // console.log(`Updating ${selectType} Engine Options. Counts Object:`, cumulativeCounts); // Optional broader log

              const options = selectElement.querySelectorAll("option");
              options.forEach((option) => {
                if (!option.value) return;
                const value = option.value; // e.g., "4.0"
                // Explicitly look up the count
                const count = cumulativeCounts[value] || 0;

                // *** Add Specific Log Here ***
                console.log(
                  `[updateEngineOptions ${selectType}] Option Value: "${value}", Count Found: ${count}`
                );
                // *** End Log ***

                // Format display text (e.g., 2.0L (15))
                const numericValue = parseFloat(value);
                const displayValueNum = number_format(numericValue, 1);
                option.textContent =
                  displayValueNum + suffix + " (" + count + ")";
                option.disabled = count === 0;
              });
            }

            updateEngineOptions(engineMinSelect, true, "L");
            updateEngineOptions(engineMaxSelect, false, "L");

            // --- Apply Min/Max Interaction Logic (Engine) ---
            if (engineMinSelect && engineMaxSelect) {
              const minValStr = engineMinSelect.value;
              const maxValStr = engineMaxSelect.value;
              const minVal = minValStr ? parseFloat(minValStr) : NaN;
              const maxVal = maxValStr ? parseFloat(maxValStr) : NaN;
              let maxResetNeeded = false;
              let minResetNeeded = false;

              // Disable max options < minVal
              engineMaxSelect.querySelectorAll("option").forEach((opt) => {
                if (!opt.value) return;
                const optVal = parseFloat(opt.value);
                // First, reset disabled state based on its own count
                opt.disabled =
                  (engineMaxCumulativeCounts[opt.value] || 0) === 0;
                // Then, disable if below the selected min value (if the option wasn't already disabled by count)
                if (!opt.disabled && !isNaN(minVal) && optVal < minVal) {
                  opt.disabled = true;
                }
                // Check if current selection needs reset (because it became disabled either by count or interaction)
                if (opt.selected && opt.disabled) {
                  maxResetNeeded = true;
                }
              });

              // Disable min options > maxVal
              engineMinSelect.querySelectorAll("option").forEach((opt) => {
                if (!opt.value) return;
                const optVal = parseFloat(opt.value);
                // First, reset disabled state based on its own count
                opt.disabled =
                  (engineMinCumulativeCounts[opt.value] || 0) === 0;
                // Then, disable if above the selected max value (if the option wasn't already disabled by count)
                if (!opt.disabled && !isNaN(maxVal) && optVal > maxVal) {
                  opt.disabled = true;
                }
                // Check if current selection needs reset
                if (opt.selected && opt.disabled) {
                  minResetNeeded = true;
                }
              });

              // Re-enable auto-reset check for Engine
              let engineMinResetNeeded = false;
              let engineMaxResetNeeded = false;
              engineMinSelect.querySelectorAll("option").forEach((opt) => {
                if (opt.selected && opt.disabled) engineMinResetNeeded = true;
              });
              engineMaxSelect.querySelectorAll("option").forEach((opt) => {
                if (opt.selected && opt.disabled) engineMaxResetNeeded = true;
              });
              if (engineMinResetNeeded) engineMinSelect.value = "";
              if (engineMaxResetNeeded) engineMaxSelect.value = "";
            }
            // --- End Engine Range Updates ---

            // --- Update Mileage Range Selects ---
            const mileageMinSelect = form.querySelector(
              'select[data-filter-key="mileage_min"]'
            );
            const mileageMaxSelect = form.querySelector(
              'select[data-filter-key="mileage_max"]'
            );
            const mileageMinCumulativeCounts =
              updatedCounts.mileage_min_cumulative_counts || {};
            const mileageMaxCumulativeCounts =
              updatedCounts.mileage_max_cumulative_counts || {};

            console.log("Min Mileage Counts:", mileageMinCumulativeCounts); // Debug
            console.log("Max Mileage Counts:", mileageMaxCumulativeCounts); // Debug

            function updateMileageOptions(selectElement, isMinSelect, suffix) {
              if (!selectElement) return;
              const cumulativeCounts = isMinSelect
                ? mileageMinCumulativeCounts
                : mileageMaxCumulativeCounts;
              const options = selectElement.querySelectorAll("option");
              options.forEach((option) => {
                if (!option.value) return;
                const value = option.value; // Expecting integer string e.g., "10000"
                const count = cumulativeCounts[value] || 0;

                // Format display text (e.g., 10,000 km (15))
                const numericValue = parseInt(value, 10);
                // Basic number format with commas for display
                const displayValueNum = numericValue.toLocaleString();
                option.textContent =
                  displayValueNum + suffix + " (" + count + ")";
                option.disabled = count === 0;
              });
            }

            updateMileageOptions(mileageMinSelect, true, " km");
            updateMileageOptions(mileageMaxSelect, false, " km");

            // --- Apply Min/Max Interaction Logic (Mileage) ---
            if (mileageMinSelect && mileageMaxSelect) {
              const minValStr = mileageMinSelect.value;
              const maxValStr = mileageMaxSelect.value;
              const minVal = minValStr ? parseInt(minValStr, 10) : NaN;
              const maxVal = maxValStr ? parseInt(maxValStr, 10) : NaN;

              // Disable max options < minVal
              mileageMaxSelect.querySelectorAll("option").forEach((opt) => {
                if (!opt.value) return;
                const optVal = parseInt(opt.value, 10);
                // Reset disabled based on count first
                opt.disabled =
                  (mileageMaxCumulativeCounts[opt.value] || 0) === 0;
                // Then disable if below min
                if (!opt.disabled && !isNaN(minVal) && optVal < minVal) {
                  opt.disabled = true;
                }
              });

              // Disable min options > maxVal
              mileageMinSelect.querySelectorAll("option").forEach((opt) => {
                if (!opt.value) return;
                const optVal = parseInt(opt.value, 10);
                // Reset disabled based on count first
                opt.disabled =
                  (mileageMinCumulativeCounts[opt.value] || 0) === 0;
                // Then disable if above max
                if (!opt.disabled && !isNaN(maxVal) && optVal > maxVal) {
                  opt.disabled = true;
                }
              });

              // Add auto-reset check for Mileage
              let mileageMinResetNeeded = false;
              let mileageMaxResetNeeded = false;
              mileageMinSelect.querySelectorAll("option").forEach((opt) => {
                if (opt.selected && opt.disabled) mileageMinResetNeeded = true;
              });
              mileageMaxSelect.querySelectorAll("option").forEach((opt) => {
                if (opt.selected && opt.disabled) mileageMaxResetNeeded = true;
              });
              if (mileageMinResetNeeded) mileageMinSelect.value = "";
              if (mileageMaxResetNeeded) mileageMaxSelect.value = "";
            }
            // --- End Mileage Range Updates ---

            // --- Update Year Range Selects ---
            const yearMinSelect = form.querySelector(
              'select[data-filter-key="year_min"]'
            );
            const yearMaxSelect = form.querySelector(
              'select[data-filter-key="year_max"]'
            );
            const yearMinCumulativeCounts =
              updatedCounts.year_min_cumulative_counts || {};
            const yearMaxCumulativeCounts =
              updatedCounts.year_max_cumulative_counts || {};

            console.log("Min Year Counts:", yearMinCumulativeCounts); // Debug
            console.log("Max Year Counts:", yearMaxCumulativeCounts); // Debug

            function updateYearOptions(selectElement, isMinSelect) {
              if (!selectElement) return;
              const cumulativeCounts = isMinSelect
                ? yearMinCumulativeCounts
                : yearMaxCumulativeCounts;
              const options = selectElement.querySelectorAll("option");
              options.forEach((option) => {
                if (!option.value) return;
                const value = option.value; // Year string e.g., "2023"
                const count = cumulativeCounts[value] || 0;
                option.textContent = value + " (" + count + ")"; // Display year and count
                option.disabled = count === 0;
              });
            }

            updateYearOptions(yearMinSelect, true);
            updateYearOptions(yearMaxSelect, false);

            // --- Apply Min/Max Interaction Logic (Year) ---
            if (yearMinSelect && yearMaxSelect) {
              const minValStr = yearMinSelect.value;
              const maxValStr = yearMaxSelect.value;
              const minVal = minValStr ? parseInt(minValStr, 10) : NaN;
              const maxVal = maxValStr ? parseInt(maxValStr, 10) : NaN;

              // Disable max options < minVal
              yearMaxSelect.querySelectorAll("option").forEach((opt) => {
                if (!opt.value) return;
                const optVal = parseInt(opt.value, 10);
                opt.disabled = (yearMaxCumulativeCounts[opt.value] || 0) === 0;
                if (!opt.disabled && !isNaN(minVal) && optVal < minVal) {
                  opt.disabled = true;
                }
              });

              // Disable min options > maxVal
              yearMinSelect.querySelectorAll("option").forEach((opt) => {
                if (!opt.value) return;
                const optVal = parseInt(opt.value, 10);
                opt.disabled = (yearMinCumulativeCounts[opt.value] || 0) === 0;
                if (!opt.disabled && !isNaN(maxVal) && optVal > maxVal) {
                  opt.disabled = true;
                }
              });

              // Add auto-reset check for Year
              let yearMinResetNeeded = false;
              let yearMaxResetNeeded = false;
              yearMinSelect.querySelectorAll("option").forEach((opt) => {
                if (opt.selected && opt.disabled) yearMinResetNeeded = true;
              });
              yearMaxSelect.querySelectorAll("option").forEach((opt) => {
                if (opt.selected && opt.disabled) yearMaxResetNeeded = true;
              });
              if (yearMinResetNeeded) yearMinSelect.value = "";
              if (yearMaxResetNeeded) yearMaxSelect.value = "";
            }
            // --- End Year Range Updates ---

            // --- Reset Standard Selects if Selection Becomes Invalid ---
            form
              .querySelectorAll("select[data-filter-key]")
              .forEach((selectElement) => {
                const filterKey = selectElement.getAttribute("data-filter-key");
                // Skip location and range inputs (handled above)
                if (
                  filterKey === "location" ||
                  filterKey.endsWith("_min") ||
                  filterKey.endsWith("_max")
                ) {
                  return;
                }
                // Check if a value is selected and if that selected option is now disabled
                if (
                  selectElement.value &&
                  selectElement.options[selectElement.selectedIndex]?.disabled
                ) {
                  console.log(`Resetting invalid selection for: ${filterKey}`); // Debug
                  selectElement.value = "";
                  // If Model/Variant was reset, potentially re-trigger AJAX to update subsequent fields?
                  // For now, just reset. A change event isn't fired automatically by setting .value
                  if (filterKey === "make") {
                    // If make was reset, disable model/variant
                    const modelSelect = form.querySelector(
                      'select[data-filter-key="model"]'
                    );
                    const variantSelect = form.querySelector(
                      'select[data-filter-key="variant"]'
                    );
                    if (modelSelect) {
                      modelSelect.innerHTML =
                        '<option value="">Select Make First</option>';
                      modelSelect.disabled = true;
                    }
                    if (variantSelect) {
                      variantSelect.innerHTML =
                        '<option value="">Select Model First</option>';
                      variantSelect.disabled = true;
                    }
                  } else if (filterKey === "model") {
                    // If model was reset, disable variant
                    const variantSelect = form.querySelector(
                      'select[data-filter-key="variant"]'
                    );
                    if (variantSelect) {
                      variantSelect.innerHTML =
                        '<option value="">Select Model First</option>';
                      variantSelect.disabled = true;
                    }
                  }
                }
              });
            // --- End Reset Standard Selects ---

            // Ensure Model/Variant selects are enabled/disabled correctly after update (redundant check?)
            const makeSelect = form.querySelector("#filter-make-" + context);
            const modelSelect = form.querySelector("#filter-model-" + context);
            const variantSelect = form.querySelector(
              "#filter-variant-" + context
            );
            if (modelSelect)
              modelSelect.disabled = !makeSelect || !makeSelect.value;
            if (variantSelect)
              variantSelect.disabled = !modelSelect || !modelSelect.value;
          } else {
            console.error(
              "AJAX error fetching filter counts:",
              result.data || "Unknown error"
            );
          }
        })
        .catch((error) => {
          console.error("Fetch error:", error);
        })
        .finally(() => {
          ajaxRequestPending = false;
        });
    }

    filterSelects.forEach((select) => {
      select.addEventListener("change", function () {
        console.log("Select changed:", this.id, "New value:", this.value); // Log select changes
        handleFilterChange();
      });
    });

    multiSelectFilters.forEach((msFilter) => {
      const display = msFilter.querySelector(".multi-select-display");
      const popup = msFilter.querySelector(".multi-select-popup");
      const checkboxes = msFilter.querySelectorAll('input[type="checkbox"]');
      const filterKey = msFilter.getAttribute("data-filter-key");
      const hiddenInput = msFilter.querySelector(".multi-select-value");

      display.addEventListener("click", (event) => {
        console.log("[MultiSelect Click] Handler START for key:", filterKey);
        event.stopPropagation();
        const isActive = msFilter.classList.contains("active");
        console.log("[MultiSelect Click] Is currently active?", isActive);

        // Close other popups
        document
          .querySelectorAll(".multi-select-filter.active")
          .forEach((activeMs) => {
            if (activeMs !== msFilter) {
              activeMs.classList.remove("active");
              const otherKey = activeMs.getAttribute("data-filter-key");
              const otherHidden = activeMs.querySelector(".multi-select-value");
              if (
                otherHidden &&
                multiSelectInitialValues[otherKey] !== otherHidden.value
              ) {
                console.log(
                  "Closing other multi-select with change:",
                  otherKey
                );
                handleFilterChange(otherKey);
              }
            }
          });

        // Toggle the current one
        console.log(
          "[MultiSelect Click] About to toggle active class on:",
          msFilter
        );
        msFilter.classList.toggle("active");
        console.log(
          "[MultiSelect Click] Class list after toggle:",
          msFilter.classList
        );

        if (msFilter.classList.contains("active")) {
          multiSelectInitialValues[filterKey] = hiddenInput
            ? hiddenInput.value
            : "";
          console.log(
            "Opened multi-select:",
            filterKey,
            "Initial value:",
            multiSelectInitialValues[filterKey]
          );
        } else {
          if (
            hiddenInput &&
            multiSelectInitialValues[filterKey] !== hiddenInput.value
          ) {
            // Check hiddenInput exists
            console.log("Closing multi-select with change:", filterKey);
            handleFilterChange(filterKey);
          } else {
            console.log("Closing multi-select without change:", filterKey);
          }
        }
      });

      checkboxes.forEach((cb) => {
        cb.addEventListener("change", () => {
          console.log(
            "Checkbox changed:",
            filterKey,
            "Value:",
            cb.value,
            "Checked:",
            cb.checked
          );
          updateMultiSelectDisplay(msFilter);
        });
      });
    });

    document.addEventListener("click", (event) => {
      const openPopup = document.querySelector(".multi-select-filter.active");
      if (openPopup && !openPopup.contains(event.target)) {
        const filterKey = openPopup.getAttribute("data-filter-key");
        console.log("Document click closing multi-select:", filterKey);
        openPopup.classList.remove("active");
        const hiddenInput = openPopup.querySelector(".multi-select-value");
        if (
          hiddenInput &&
          multiSelectInitialValues[filterKey] !== hiddenInput.value
        ) {
          console.log(
            "Closing multi-select via doc click with change:",
            filterKey
          );
          handleFilterChange(filterKey);
        }
      }
    });

    if (resetButton) {
      resetButton.addEventListener("click", () => {
        console.log("Reset button clicked");
        form.reset();
        multiSelectFilters.forEach((msFilter) => {
          const hiddenInput = msFilter.querySelector(".multi-select-value");
          if (hiddenInput) hiddenInput.value = "";
          msFilter
            .querySelectorAll('input[type="checkbox"]')
            .forEach((cb) => (cb.checked = false));
          updateMultiSelectDisplay(msFilter);
        });
        handleFilterChange();
      });
    }

    const initialMakeSelect = form.querySelector("#filter-make-" + context);
    const initialModelSelect = form.querySelector("#filter-model-" + context);
    const initialVariantSelect = form.querySelector(
      "#filter-variant-" + context
    );

    const initialMake = initialMakeSelect ? initialMakeSelect.value : "";
    const initialModel = initialModelSelect ? initialModelSelect.value : "";

    if (initialModelSelect && !initialMake) {
      initialModelSelect.disabled = true;
    }
    if (initialVariantSelect && !initialModel) {
      initialVariantSelect.disabled = true;
    }
    // Initial setup for multi-select display text
    multiSelectFilters.forEach((msFilter) => {
      updateMultiSelectDisplay(msFilter);
    });

    // --- Override Form Submission ---
    if (form) {
      form.addEventListener("submit", function (event) {
        event.preventDefault(); // Stop the default GET submission
        console.log("Form submission intercepted.");

        const currentFilters = getCurrentFilters(); // Use existing helper
        const params = new URLSearchParams();

        // Add only non-empty filters to the URL parameters
        for (const key in currentFilters) {
          if (currentFilters[key] && currentFilters[key] !== "") {
            params.append(key, currentFilters[key]);
          }
        }

        // Construct the final URL
        const baseUrl = form.action; // Get the action URL from the form (/car_listings/)
        const queryString = params.toString();
        const finalUrl = baseUrl + (queryString ? "?" + queryString : ""); // Add query string only if not empty

        console.log("Redirecting to:", finalUrl);
        window.location.href = finalUrl; // Redirect the browser
      });
    }
    // --- End Override Form Submission ---

    console.log(
      "DOM ready. Running initial handleFilterChange to get base counts."
    );
    handleFilterChange();
  }); // End DOMContentLoaded listener
})(jQuery); // End jQuery no-conflict wrapper and pass jQuery
