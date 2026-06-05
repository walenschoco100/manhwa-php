const form = document.querySelector("#loginForm");
const username = document.querySelector("#usernameInput");
const password = document.querySelector("#passwordInput");
const error = document.querySelector("#loginError");

form.addEventListener("submit", async event => {
  event.preventDefault();
  error.hidden = true;

  const response = await fetch("/api/admin-login", {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({
      username: username.value.trim(),
      password: password.value
    })
  });
  const data = await response.json();
  if (!response.ok || !data.ok) {
    error.textContent = data.error || "Login gagal.";
    error.hidden = false;
    return;
  }

  const next = new URL(location.href).searchParams.get("next") || "/admin";
  location.href = next.startsWith("/admin") ? next : "/admin";
});
