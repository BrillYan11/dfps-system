/**
 * Dynamic location loader using includes/locations_api.php (proxying PSGC API)
 */
async function loadSelect(url, selectEl, placeholder) {
  if (!selectEl) return;
  selectEl.innerHTML = `<option value="">${placeholder}</option>`;
  
  try {
    const res = await fetch(url);
    const data = await res.json();
    if (data.error) { 
      console.error(data.error); 
      return; 
    }

    data.forEach(item => {
      const opt = document.createElement("option");
      opt.value = item.code;
      opt.textContent = item.name;
      selectEl.appendChild(opt);
    });
  } catch (error) {
    console.error("Error loading location data:", error);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const regionEl = document.getElementById("region");
  const provEl = document.getElementById("province");
  const cityEl = document.getElementById("city");
  const cityNameInput = document.getElementById("city_name");

  if (regionEl) {
    loadSelect("includes/locations_api.php?action=regions", regionEl, "Select region");

    regionEl.addEventListener("change", async () => {
      if (provEl) {
        provEl.disabled = false;
        if (cityEl) {
          cityEl.disabled = true;
          cityEl.innerHTML = `<option value="">Select city/municipality</option>`;
        }
        await loadSelect(`includes/locations_api.php?action=provinces&region_id=${encodeURIComponent(regionEl.value)}`, provEl, "Select province");
      }
    });
  }

  if (provEl) {
    provEl.addEventListener("change", async () => {
      if (cityEl) {
        cityEl.disabled = false;
        await loadSelect(`includes/locations_api.php?action=cities&province_id=${encodeURIComponent(provEl.value)}`, cityEl, "Select city/municipality");
      }
    });
  }

  if (cityEl && cityNameInput) {
    cityEl.addEventListener("change", () => {
      const selectedOption = cityEl.options[cityEl.selectedIndex];
      if (selectedOption && selectedOption.value !== "") {
        cityNameInput.value = selectedOption.text;
      } else {
        cityNameInput.value = "";
      }
    });
  }
});
