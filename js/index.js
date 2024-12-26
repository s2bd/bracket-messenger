function toggleForm() {
    const loginContainer = document.getElementById('loginContainer');
    const registerContainer = document.getElementById('registerContainer');
    loginContainer.style.display = loginContainer.style.display === 'none' ? 'block' : 'none';
    registerContainer.style.display = registerContainer.style.display === 'none' ? 'block' : 'none';
}

function toggleAboutPanel() {
    const aboutPanel = document.querySelector('.about-panel');
    if (!aboutPanel.classList.contains('expanded')) {
        aboutPanel.innerHTML = `
            About this platform - by Group 3
            <br><br>
            Made by <a href="https://www.facebook.com/profile.php?id=100018989650811" target="_blank">Ahmed Unais</a>, 
            <a href="https://www.facebook.com/sabiqzahid11" target="_blank">Sabiq Zahid</a>, 
            <a href="https://www.facebook.com/dmimukto/" target="_blank">Dewan Mukto</a>
            <br>As a group project for CSE370-20
        `;
        aboutPanel.classList.add('expanded');
    } else {
        aboutPanel.innerHTML = 'About this platform';
        aboutPanel.classList.remove('expanded');
    }
}

const container = document.querySelector('.container');

        function toggleMode() {
            container.classList.toggle('register-active');
        }
document.body.addEventListener('mousemove', (event) => {
    document.body.style.setProperty('--mouse-x', `${event.clientX}px`);
    document.body.style.setProperty('--mouse-y', `${event.clientY}px`);
});