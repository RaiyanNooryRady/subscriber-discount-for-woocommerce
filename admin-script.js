jQuery(document).ready(function ($) {
    // Delete subscriber functionality
    $(".delete-subscriber").click(function () {
        var subscriberId = $(this).data("id");
        var nonce = sdw_ajax.nonce; // Security nonce
        if (!confirm("Are you sure you want to delete this subscriber?")) {
            return;
        }

        $.ajax({
            type: "POST",
            url: sdw_ajax.ajax_url,
            data: {
                action: "sdw_delete_subscriber",
                subscriber_id: subscriberId,
                nonce: nonce,
            },
            success: function (response) {
                if (response.success) {
                    $("#subscriber-" + subscriberId).fadeOut("slow", function () {
                        $(this).remove();
                    });
                } else {
                    alert("Error: " + response.data.message);
                }
            },
            error: function () {
                alert("An error occurred. Please try again.");
            },
        });
    });
});
