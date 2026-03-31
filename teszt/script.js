// script.js

document.addEventListener('DOMContentLoaded', () => {
    console.log('Szívhangja oldal betöltve.');

    // Űrlap validáció visszajelzés (egyszerű példa)
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            // Itt történne a tényleges küldés
            alert('Köszönjük a regisztrációt! (Ez egy demó üzenet)');
            form.reset();
        });
    });

    // Mobil menü kezelése (ha később bővülne a navigáció)
    // Jelenleg egyszerű, de ide kerülne a hamburger menü logikája.
});
