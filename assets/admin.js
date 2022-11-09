jQuery(function ($) {
	var location_restrictions_group = document.querySelector(
		".woocommerce-coupon-restrictions-locations"
	);
	var location_restrictions_cb = document.querySelector(
		"#location_restrictions"
	);

	if (location_restrictions_cb.checked) {
		location_restrictions_group.removeAttribute("style");
	}

	location_restrictions_cb.addEventListener("change", function () {
		if (this.checked) {
			location_restrictions_group.removeAttribute("style");
		} else {
			location_restrictions_group.style.display = "none";
		}
	});

	document
		.querySelector("#wccr-add-all-countries")
		.addEventListener("click", function () {
			$("#wccr-restricted-countries")
				.select2("destroy")
				.find("option")
				.prop("selected", "selected")
				.end()
				.select2();
		});

	document
		.querySelector("#wccr-clear-all-countries")
		.addEventListener("click", function () {
			$("#wccr-restricted-countries")
				.select2("destroy")
				.find("option")
				.prop("selected", false)
				.end()
				.select2();
		});
});
