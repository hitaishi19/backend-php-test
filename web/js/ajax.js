// Add an event listener to fetch todo data
$(document).on("DOMContentLoaded", function (e) {
    var url = $('#ajax-url').data('url');
    // Send AJAX request to fetch todo details
    $.ajax({
        url: url,
        method: "GET",
        dataType: "json",
        success: function (response) {
            // Update the content with the fetched todo details
            $(".todo-table-data").html(response.html);
        },
        error: function (xhr, status, error) {
            console.error("Error fetching todo details:", error);
            // Handle error if necessary
        }
    });
});

// Handle form submission for delete action
$(document).on("click", ".remove-todo, .complete-todo", function (e) {
    e.preventDefault();
    $(this).attr('disabled', true);
    $('#message, #error').hide();
    // Get the todo ID from the button's data attribute
    const todoId = $(this).data("todo-id");

    // Reference to the current button element
    const $button = $(this);

    // Send AJAX request
    $.ajax({
        url: $button.closest("form").attr("action"),
        method: "POST",
        data: { todoId: todoId },
        dataType: "json",
        success: function (response) {
            // Handle success response
            $('#message').text(response.message);
            $('#message').show();

            if ($button.hasClass('remove-todo')) {
                // remove the deleted todo row from the table
                $button.closest("tr").slideUp("slow", function() {
                    $(this).remove();
                });
            } else if ($button.hasClass('complete-todo')) {
                $button.fadeOut(function() {
                    $(this).remove();
                });
                $('#completed').fadeOut(function() {
                    $(this).show();
                });
            }
            $(document).trigger("DOMContentLoaded");
            $(this).attr('disabled', false);
        },
        error: function (xhr, status, error) {
            // Handle error response
            $('#error').text(error);
            $('#error').show();
        }
    });
});

// Handle form submission for add action
$(document).on("click", ".add-todo", function (e) {
    e.preventDefault();
    $('#message, #error').hide();
    $(this).attr('disabled', true);

    // Get the form data
    var formData = $(".add-form").serialize();

    // Send AJAX request to add the todo
    $.ajax({
        url: $(".add-form").attr("action"),
        method: "POST",
        data: formData,
        dataType: "json",
        success: function (response) {
            // Handle success response
            if(response.error){
                $('#error').text(response.error);
                $('#error').show();
            } else {
                $('#message').text(response.message);
                $('#message').show();
                // Clear the input field after adding the todo
                $(".add-form input[name='description']").val("");
                $(document).trigger("DOMContentLoaded");
            }
            $(this).attr('disabled', true);
        },
        error: function (xhr, status, error) {
            // Handle error response
            $('#error').text(error);
            $('#error').show();
        }
    });
})

