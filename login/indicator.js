function detectRole() {
  const email = document.getElementById("email").value;
  const bar   = document.getElementById("roleBar");
  const dot   = document.getElementById("roleDot");
  const text  = document.getElementById("roleText");

  const set = (label, color, borderColor) => {
    text.innerText = label;
    dot.style.background = color;
    text.style.color = color;
    bar.style.borderColor = borderColor || '#3a3a3a';
  };

  if (email.includes("admin")) {
    set("Admin Access", "#d4a843", "#4a3a1a");
  } else if (email.includes("hr")) {
    set("HR Access", "#c0c0c0", "#4a4a4a");
  } else if (email.length > 3) {
    set("Employee Access", "#6a9fd8", "#1a2a3a");
  } else {
    set("Detecting role…", "#555555", "#2a2a2a");
  }
}

function showPopup(msg) {
  document.getElementById("popupMessage").innerText = msg;
  document.getElementById("popup").style.display = "flex";
}

function closePopup() {
  document.getElementById("popup").style.display = "none";
}