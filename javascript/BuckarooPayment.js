jQuery(document).ready(
	function() {
		jQuery("input[name=BuckarooMethod]").change(
			function() {
				var val = jQuery("input[name=BuckarooMethod]:checked").val();
				jQuery("#OrderForm_OrderForm_Amount").addClass("loading").html("<i>opnieuw berekenen</i>");
				jQuery.get(
					"/updatebuckaroopaymentchoice/update/",
					{ paymentoption: val},
					function(data){
						EcomCart.setChanges(data);
						jQuery("#OrderForm_OrderForm_Amount").removeClass("loading");
						if(jQuery("#OrderForm_OrderForm_Amount").html == "<i>opnieuw berekenen</i>") {
							window.location.reload();
						}
					},
					"json"
				);
			}
		)

	}
)
