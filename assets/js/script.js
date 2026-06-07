// script.js

// Navbar toggle for mobile (optional)
const navToggle = document.getElementById('nav-toggle');
const navLinks = document.querySelector('.nav-links');
if (navToggle) {
  navToggle.addEventListener('click', () => {
    navLinks.classList.toggle('open');
  });
}

// "Mulai Chat" button action
const startChatBtn = document.getElementById('start-chat');
if (startChatBtn) {
  startChatBtn.addEventListener('click', () => {
    alert('Fitur chatbot akan segera hadir!');
    // window.location.href = '/chat'; // future route
  });
}
