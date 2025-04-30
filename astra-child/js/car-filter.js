document.addEventListener("DOMContentLoaded", function () {
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

  const form = document.getElementById("car-filter-form-" + context);
  // Ensure form exists before proceeding
  if (!form) {
    // console.error(`Car Filter Error: Form with ID 'car-filter-form-${context}' not found.`);
    // Don't log error here, form might not be present on all pages using this script
    return;
  }

  const container = form.closest(".car-filter-form-container");
  const filterSelects = form.querySelectorAll("select[data-filter-key]");
  const multiSelectFilters = form.querySelectorAll(".multi-select-filter"); // Get multi-selects
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
    const hiddenInput = multiSelectElement.querySelector(".multi-select-value");
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

    // console.log("Triggering AJAX Update. Filters:", getCurrentFilters()); // Debugging

    const currentFilters = getCurrentFilters();

    const formData = new FormData();
    formData.append("action", updateAction);
    formData.append("nonce", updateNonce);
    for (const key in currentFilters) {
      formData.append(`filters[${key}]`, currentFilters[key]);
    }

    // --- Add console log before fetch ---
    console.log(
      "handleFilterChange: Attempting fetch to",
      ajaxUrl,
      "Action:",
      updateAction
    );
    console.log("Current Filters Sent:", currentFilters);
    console.log("Nonce Sent:", updateNonce);
    console.log("ajaxRequestPending:", ajaxRequestPending);
    // --- End log ---

    fetch(ajaxUrl, { method: "POST", body: formData })
      .then((response) => {
        console.log("Fetch response received:", response); // Log response object
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then((result) => {
        console.log("Parsed JSON result:", result); // Log parsed result
        if (result.success && result.data) {
          const updatedCounts = result.data;
          // console.log("Received updated counts:", updatedCounts); // Debugging

          // Update each filter element (standard selects and multi-selects)
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
                // ... Cases for make, model, variant (as before)
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
                  if (selectedMake && makeModelVariantStructure[selectedMake]) {
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
                  ?.querySelector(".option-count"); // Optional chaining
                if (countSpan) {
                  countSpan.textContent = count;
                }
                // DO NOT disable checkbox based on count per user request
                // cb.disabled = (count === 0); // <-- Removed/Commented
              });
              // We don't need to call updateMultiSelectDisplay here, as the selections
              // themselves haven't changed, only the counts beside them.
            }
          });

          // --- Update Engine Range Selects ---
          // const engineCounts = updatedCounts.engine_capacity_counts || {}; // Exact counts - currently unused in JS display
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
            if (!selectElement) return; // Exit if select not found
            const cumulativeCounts = isMinSelect
              ? engineMinCumulativeCounts
              : engineMaxCumulativeCounts;
            const options = selectElement.querySelectorAll("option");
            options.forEach((option) => {
              if (!option.value) return; // Skip default "Min/Max Size"
              const value = option.value; // e.g., "1.0", "2.0"
              // *** IMPORTANT: Log the value from option and the lookup ***
              // console.log(`Checking option value: "${value}", Type: ${typeof value}`);
              // console.log(`Looking up count in cumulativeCounts["${value}"]`);
              const count = cumulativeCounts[value] || 0;
              // console.log(`Count found: ${count}`);
              // *** End log ***

              // Format display text (e.g., 2.0L (15))
              const numericValue = parseFloat(value);
              // Correct formatting: show .0 only if not a whole number originally or if value is 0.0
              const displayValueNum = number_format(numericValue, 1); // Always format to 1 decimal for display consistency? Or only if needed? Let's try 1 always for now.
              // Original logic: const displayValueNum = (numericValue == Math.floor(numericValue) && numericValue !== 0.0) ? number_format(numericValue, 0) : number_format(numericValue, 1);
              option.textContent =
                displayValueNum + suffix + " (" + count + ")";
              // Disable based on cumulative count
              option.disabled = count === 0;
              // If it becomes disabled and is selected, reset parent select (handled below)
            });
          }

          updateEngineOptions(engineMinSelect, true, "L");
          updateEngineOptions(engineMaxSelect, false, "L");

          // --- Apply Min/Max Interaction Logic ---
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
              opt.disabled = (engineMaxCumulativeCounts[opt.value] || 0) === 0;
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
              opt.disabled = (engineMinCumulativeCounts[opt.value] || 0) === 0;
              // Then, disable if above the selected max value (if the option wasn't already disabled by count)
              if (!opt.disabled && !isNaN(maxVal) && optVal > maxVal) {
                opt.disabled = true;
              }
              // Check if current selection needs reset
              if (opt.selected && opt.disabled) {
                minResetNeeded = true;
              }
            });

            // Reset if necessary
            if (maxResetNeeded) engineMaxSelect.value = "";
            if (minResetNeeded) engineMinSelect.value = "";
          }
          // --- End Engine Range Updates ---

          // Ensure Model/Variant selects are enabled/disabled correctly after update
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
        console.error("Fetch error:", error); // Log fetch errors
      })
      .finally(() => {
        ajaxRequestPending = false; // Allow next request
        console.log("Fetch finished. ajaxRequestPending:", ajaxRequestPending); // Log final flag state
      });
  }

  // --- Event Listeners for Standard Selects ---
  filterSelects.forEach((select) => {
    select.addEventListener("change", function () {
      console.log("Select changed:", this.id, "New value:", this.value); // Log select changes
      handleFilterChange();
    });
  });

  // --- Event Listeners for Multi-Select Filters ---
  multiSelectFilters.forEach((msFilter) => {
    const display = msFilter.querySelector(".multi-select-display");
    const popup = msFilter.querySelector(".multi-select-popup");
    const checkboxes = msFilter.querySelectorAll('input[type="checkbox"]');
    const filterKey = msFilter.getAttribute("data-filter-key");
    const hiddenInput = msFilter.querySelector(".multi-select-value");

    // Toggle Popup
    display.addEventListener("click", (event) => {
      console.log("Multi-select display clicked:", filterKey); // Log clicks
      event.stopPropagation(); // Prevent closing immediately via document listener
      const isActive = msFilter.classList.contains("active");
      // Close all other popups first
      document
        .querySelectorAll(".multi-select-filter.active")
        .forEach((activeMs) => {
          if (activeMs !== msFilter) {
            activeMs.classList.remove("active");
            // Check if value changed on close and trigger AJAX if needed
            const otherKey = activeMs.getAttribute("data-filter-key");
            const otherHidden = activeMs.querySelector(".multi-select-value");
            if (
              otherHidden &&
              multiSelectInitialValues[otherKey] !== otherHidden.value
            ) {
              // Check otherHidden exists
              console.log("Closing other multi-select with change:", otherKey);
              handleFilterChange(otherKey);
            }
          }
        });
      // Toggle current popup
      msFilter.classList.toggle("active");
      if (msFilter.classList.contains("active")) {
        // Store current value when opened
        multiSelectInitialValues[filterKey] = hiddenInput
          ? hiddenInput.value
          : ""; // Check hiddenInput exists
        console.log(
          "Opened multi-select:",
          filterKey,
          "Initial value:",
          multiSelectInitialValues[filterKey]
        );
      } else {
        // Check if value changed on close and trigger AJAX if needed
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

    // Handle Checkbox Changes (Update Display/Hidden Input ONLY)
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
        // DO NOT trigger handleFilterChange here
      });
    });
  });

  // --- Global Click Listener to Close Popups ---
  document.addEventListener("click", (event) => {
    const openPopup = document.querySelector(".multi-select-filter.active");
    if (openPopup && !openPopup.contains(event.target)) {
      const filterKey = openPopup.getAttribute("data-filter-key");
      console.log("Document click closing multi-select:", filterKey);
      openPopup.classList.remove("active");
      // Check if value changed on close and trigger AJAX if needed
      const hiddenInput = openPopup.querySelector(".multi-select-value");
      if (
        hiddenInput &&
        multiSelectInitialValues[filterKey] !== hiddenInput.value
      ) {
        // Check hiddenInput exists
        console.log(
          "Closing multi-select via doc click with change:",
          filterKey
        );
        handleFilterChange(filterKey);
      }
    }
  });

  // --- Reset Button Listener (Optional) ---
  if (resetButton) {
    resetButton.addEventListener("click", () => {
      console.log("Reset button clicked"); // Log reset
      form.reset(); // Reset native form elements
      // Also manually reset multi-selects
      multiSelectFilters.forEach((msFilter) => {
        const hiddenInput = msFilter.querySelector(".multi-select-value");
        if (hiddenInput) hiddenInput.value = ""; // Check hiddenInput exists
        msFilter
          .querySelectorAll('input[type="checkbox"]')
          .forEach((cb) => (cb.checked = false));
        updateMultiSelectDisplay(msFilter); // Update display to default
      });
      // Manually trigger update after reset to refresh counts/options
      handleFilterChange();
    });
  }

  // --- Initial Setup ---
  // Disable Model/Variant initially if Make/Model aren't pre-selected
  const initialMakeSelect = form.querySelector("#filter-make-" + context);
  const initialModelSelect = form.querySelector("#filter-model-" + context);
  const initialVariantSelect = form.querySelector("#filter-variant-" + context);

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

  // --- New Logic: Always run initial update on page load ---
  console.log(
    "DOM ready. Running initial handleFilterChange to get base counts."
  );
  handleFilterChange();
  // --- End New Logic ---
});
