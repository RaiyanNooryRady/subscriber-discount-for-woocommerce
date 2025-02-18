jQuery(document).ready(function ($) {
    $("#sdw-subscribe-form").submit(function (event) {
        event.preventDefault();

        var email = $("#sdw-email").val();
        var nonce = sdw_ajax.nonce; // Security nonce

        if (!email) {
            $("#sdw-message").text("Please enter a valid email.").css("color", "red");
            return;
        }

        $.ajax({
            type: "POST",
            url: sdw_ajax.ajax_url,
            data: {
                action: "sdw_subscribe",
                email: email,
                nonce: nonce,
            },
            beforeSend: function () {
                $("#sdw-message").text("Processing...").css("color", "blue");
            },
            success: function (response) {
                if (response.success) {
                    $("#sdw-message").text(response.data.message).css("color", "green");
                    $("#sdw-email").val(""); // Clear input
                } else {
                    $("#sdw-message").text(response.data.message).css("color", "red");
                }
            },
            error: function () {
                $("#sdw-message").text("An error occurred. Please try again.").css("color", "red");
            },
        });
    });
});
