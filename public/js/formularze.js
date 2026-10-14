document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-takie-samo-jak]').forEach((repeat) => {
        const original = document.getElementById(repeat.dataset.takieSamoJak);
        if (!original) return;

        const check = () => {
            const same = repeat.value === original.value;
            repeat.setCustomValidity(same ? '' : 'Hasła nie są takie same.');
        };

        repeat.addEventListener('input', check);
        original.addEventListener('input', check);
    });

    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pole__pokaz';
        button.textContent = 'Pokaż';
        button.setAttribute('aria-label', 'Pokaż hasło');

        button.addEventListener('click', () => {
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            button.textContent = hidden ? 'Ukryj' : 'Pokaż';
            button.setAttribute('aria-label', hidden ? 'Ukryj hasło' : 'Pokaż hasło');
        });

        const wrapper = document.createElement('div');
        wrapper.className = 'pole__haslo';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        wrapper.appendChild(button);
    });
});
