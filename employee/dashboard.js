function toggleNotif() {
  const box = document.getElementById("notifBox");
  box.style.display = box.style.display === "block" ? "none" : "block";

  // mark as read when opened
  if (box.style.display === "block") {
    fetch('mark_read.php');
  }
}