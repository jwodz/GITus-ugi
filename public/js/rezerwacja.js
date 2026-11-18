document.addEventListener('DOMContentLoaded', () => {
    const calendar = document.querySelector('.kalendarz[data-api]');
    const box = document.getElementById('godziny');
    if (!calendar || !box) return;

    const linkTemplate = calendar.dataset.linkGodziny;

    const element = (tag, className, text) => {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    };

    const render = (data) => {
        box.replaceChildren(element('h2', '', data.dataTekst));

        if (data.godziny.length === 0) {
            box.appendChild(element('p', 'pusto', 'W tym dniu nie ma już wolnych godzin.'));
            return;
        }

        const list = element('div', 'godziny-wybor__lista');
        data.godziny.forEach((slot) => {
            const link = element('a', 'przycisk-godzina', slot.od);
            link.href = linkTemplate.replace('__DATA__', data.data).replace('__GODZINA__', slot.od);
            link.appendChild(element('small', '', '–' + slot.do));
            list.appendChild(link);
        });
        box.appendChild(list);
    };

    calendar.addEventListener('click', async (event) => {
        const day = event.target.closest('a[data-data]');
        if (!day) return;

        event.preventDefault();
        calendar.querySelectorAll('.kalendarz__dzien--wybrany').forEach((el) => {
            el.classList.remove('kalendarz__dzien--wybrany');
            el.removeAttribute('aria-current');
        });
        day.classList.add('kalendarz__dzien--wybrany');
        day.setAttribute('aria-current', 'date');
        box.replaceChildren(element('p', 'pusto', 'Ładowanie wolnych godzin…'));

        try {
            const response = await fetch(calendar.dataset.api + '&data=' + encodeURIComponent(day.dataset.data), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (!response.ok) {
                box.replaceChildren(element('p', 'komunikat komunikat--blad', data.blad || 'Nie udało się pobrać godzin.'));
                return;
            }
            render(data);
            history.replaceState(null, '', day.href);
            if (window.innerWidth < 900) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (error) {
            window.location.href = day.href;
        }
    });
});
