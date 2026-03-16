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

// Refresh CSRF token every 117 minutes (just before 120-minute session lifetime)
setInterval(function () {
    fetch("/csrf-token", {
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
    })
        .then(function (response) {
            if (response.ok) {
                return response.json();
            }
        })
        .then(function (data) {
            if (data && data.csrf_token) {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) {
                    meta.setAttribute("content", data.csrf_token);
                }
            }
        });
}, 7020000);
