document.addEventListener("turbo:before-fetch-response", function (event) {
    var response = event.detail.fetchResponse.response;
    if (response.status === 419) {
        event.preventDefault();
        window.location.reload();
    }
});

document.addEventListener("turbo:before-fetch-request", function (event) {
    var token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
        event.detail.fetchOptions.headers["X-CSRF-TOKEN"] =
            token.getAttribute("content");
    }
});
