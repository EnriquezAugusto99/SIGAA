// ===== SIDEBAR TOGGLE =====
const menuToggle = document.getElementById("menuToggle");
const sidebar = document.getElementById("sidebar");
const closeMenu = document.getElementById("closeMenu");
const overlay = document.getElementById("overlay");

function openSidebar() {
    sidebar.classList.add("abierto");
    overlay.classList.add("activo");
    document.body.style.overflow = "hidden";
}

function closeSidebarFunc() {
    sidebar.classList.remove("abierto");
    overlay.classList.remove("activo");
    document.body.style.overflow = "";
}

if (menuToggle) {
    menuToggle.addEventListener("click", openSidebar);
}

if (closeMenu) {
    closeMenu.addEventListener("click", closeSidebarFunc);
}

if (overlay) {
    overlay.addEventListener("click", closeSidebarFunc);
}

// ===== BOTONES DEL MENÚ =====
const menuLinks = document.querySelectorAll(".menu-link-principal");

menuLinks.forEach(link => {
    link.addEventListener("click", function(e) {
        const href = this.getAttribute("href");
        
        if (href === "#inicio") {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: "smooth" });
            closeSidebarFunc();
        } else {
            // Para Materias, Alumnos, Noticias, Contacto - solo cerrar sidebar
            e.preventDefault();
            closeSidebarFunc();
        }
    });
});

// ===== SLIDER AUTOMÁTICO PARA EL HEADER =====
const slides = document.querySelectorAll(".slide");
let currentSlide = 0;

function nextSlide() {
    if (slides.length > 0) {
        slides[currentSlide].classList.remove("active");
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add("active");
    }
}

if (slides.length > 0) {
    setInterval(nextSlide, 5000);
}

// ===== POPUP FLOTANTE =====
const floatingBtn = document.getElementById("floatingBtn");
const popupOverlay = document.getElementById("popupOverlay");
const closePopupBtn = document.getElementById("closePopupBtn");

if (floatingBtn) {
    floatingBtn.addEventListener("click", (e) => {
        e.stopPropagation();
        if (popupOverlay) {
            popupOverlay.classList.add("active");
        }
    });
}

if (closePopupBtn) {
    closePopupBtn.addEventListener("click", () => {
        if (popupOverlay) {
            popupOverlay.classList.remove("active");
        }
    });
}

if (popupOverlay) {
    popupOverlay.addEventListener("click", (e) => {
        if (e.target === popupOverlay) {
            popupOverlay.classList.remove("active");
        }
    });
}

// ===== BOTÓN DE INSCRIPCIÓN EN POPUP =====
const btnInscripcion = document.querySelector(".btn-inscripcion");
if (btnInscripcion) {
    btnInscripcion.addEventListener("click", (e) => {
        e.preventDefault();
        alert("📋 Formulario de preinscripción: próximamente habilitado. ¡Gracias por tu interés!");
        if (popupOverlay) {
            popupOverlay.classList.remove("active");
        }
    });
}