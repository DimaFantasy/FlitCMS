function setLanguage(lang) {
    // Проверяем, существует ли переменная langCookieName и передан ли язык
    if (typeof langCookieName !== 'undefined' && lang) {
        // Устанавливаем куки с новым языком
        document.cookie = `${langCookieName}=${lang};path=/`; // Устанавливаем куку с языком
        window.location.reload(); // Перезагружаем страницу после смены языка
    } else {
        console.log("Не передан язык или переменная langCookieName не существует.");
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Проверяем, существует ли переменная langCookieName
    if (typeof langCookieName !== 'undefined') {
        // Используем глобальную переменную для имени куки
        const langCookieName = window.langCookieName;

        // Используем регулярное выражение для получения значения куки с именем, переданным с сервера
        let currentLang = document.cookie.match(new RegExp(`${langCookieName}=([a-z]+)`));

        let langDisplay = document.getElementById("currentLang");
        let langFlag = document.getElementById("currentLangFlag");

        if (currentLang && langDisplay && langFlag) {
            switch (currentLang[1]) {
                case "ru":
                    langFlag.src = "assets/images/Flag_of_Russia.svg"; // Путь к флагу России
                    langFlag.alt = "RU"; // Обновляем атрибут alt
                    langDisplay.innerHTML = `<img id="currentLangFlag" src="${langFlag.src}" alt="${langFlag.alt}"> Русский`; // Обновляем только текст и флаг
                    break;
                case "en":
                    langFlag.src = "assets/images/Flag_of_the_United_States.svg"; // Путь к флагу США
                    langFlag.alt = "EN"; // Обновляем атрибут alt
                    langDisplay.innerHTML = `<img id="currentLangFlag" src="${langFlag.src}" alt="${langFlag.alt}"> English`; // Обновляем только текст и флаг
                    break;
                case "de":
                    langFlag.src = "assets/images/Flag_of_Germany.svg"; // Путь к флагу Германии
                    langFlag.alt = "DE"; // Обновляем атрибут alt
                    langDisplay.innerHTML = `<img id="currentLangFlag" src="${langFlag.src}" alt="${langFlag.alt}"> Deutsch`; // Обновляем только текст и флаг
                    break;
                default:
                    langFlag.src = ""; // Если язык не найден, очищаем флаг
                    langDisplay.innerHTML = ""; // Очищаем текст и флаг
            }
        } else {
            console.log("Элемент с id 'currentLang' или 'currentLangFlag' не найден.");
        }
    } else {
        console.log("Переменная langCookieName не определена.");
    }
});



document.addEventListener("DOMContentLoaded", function () {
    // Проверяем, существует ли переключатель темы
    const themeSwitch = document.getElementById("themeSwitch");
    if (themeSwitch) {
        const themeLabel = document.getElementById("themeLabel");
        const navbar = document.getElementById("navbar");
        const footer = document.getElementById("footer");
        const langButton = document.getElementById("langDropdown"); // Кнопка переключателя языка

        function applyTheme(theme) {
            // Применяем тему ко всему документу
            document.documentElement.setAttribute('data-bs-theme', theme);
            // Обновляем навбар
            if (navbar) {
                if (theme === 'dark') {
                    navbar.classList.remove('navbar-light', 'bg-light');
                    navbar.classList.add('navbar-dark', 'bg-dark', 'shadow');
                } else {
                    navbar.classList.remove('navbar-dark', 'bg-dark');
                    navbar.classList.add('navbar-light', 'bg-light', 'shadow');
                }
            }
            // Обновляем футер
            if (footer) {
                if (theme === 'dark') {
                    footer.classList.remove('bg-light', 'text-dark');
                    footer.classList.add('bg-dark', 'text-white', 'shadow');
                } else {
                    footer.classList.remove('bg-dark', 'text-white');
                    footer.classList.add('bg-light', 'text-dark', 'shadow');
                }
            }
            // Обновляем кнопку переключателя языка
            if (langButton) {
                if (theme === 'dark') {
                    langButton.classList.remove('btn-light');
                    langButton.classList.add('btn-dark');
                } else {
                    langButton.classList.remove('btn-dark');
                    langButton.classList.add('btn-light');
                }
            }
            // Обновляем текст переключателя темы
            if (themeLabel) {
                themeLabel.textContent = theme === 'dark' ? 'Темная тема' : 'Светлая тема';
            }
        }
        // Загружаем сохраненную тему из localStorage
        const savedTheme = localStorage.getItem('theme') || 'light';
        applyTheme(savedTheme);
        themeSwitch.checked = savedTheme === 'dark';
        // Обработчик изменения темы
        themeSwitch.addEventListener('change', function () {
            const theme = this.checked ? 'dark' : 'light';
            applyTheme(theme);
            localStorage.setItem('theme', theme);
        });
    }
});
