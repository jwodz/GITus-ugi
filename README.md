# SwissCar Import – system rezerwacji usług

# Autorzy

Imie i Nazwisko / GitHub
Jan Wodzyński / jwodz
Maciej Bieliński / Makro201
5KT

# Opis systemu

SwissCar Import to strona internetowa dla firmy, która sprowadza samochody ze Szwajcarii do Polski. Klient może zobaczyć ofertę i zarezerwować termin u wybranego pracownika, np. na konsultację, sprawdzenie historii auta po numerze VIN, przygotowanie dokumentów do cła, pomoc w rejestracji auta albo odbiór auta.

Sprowadzenie auta trwa kilka tygodni, dlatego w systemie rezerwuje się pojedyncze etapy. Każdy etap to spotkanie lub usługa, która trwa określony czas i jest wykonywana przez konkretnego pracownika w jego godzinach pracy.

W systemie są trzy rodzaje użytkowników:

- **Klient** – zakłada konto, przegląda ofertę, rezerwuje termin (usługa → pracownik → data → godzina → potwierdzenie) w graficznym kalendarzu, widzi swoje rezerwacje, może anulować lub przełożyć przyszłą wizytę, pobrać potwierdzenie (wydruk / PDF) i dodać wizytę do kalendarza w telefonie.
- **Pracownik** – widzi swoje wizyty na dziś, przyszłe wizyty i historię, szczegóły wizyty i dane klienta, zmienia status obsługiwanych wizyt, widzi swój grafik i dni wolne.
- **Administrator** – zarządza użytkownikami, pracownikami, kategoriami, usługami i godzinami pracy, widzi wszystkie rezerwacje i statystyki.

# Technologie

- PHP
- HTML5, CSS3, JavaScript
- MySQL
- Git i GitHub

# Struktura repozytorium

```
README.md
.gitignore
index.php                 – przekierowanie do folderu public
/config
    config.example.php    – wzór pliku z ustawieniami (bez haseł)
/database
    database.sql          – baza danych z danymi testowymi
/docs
    erd.png               – schemat bazy danych
/includes                 – wspólny kod PHP
    bootstrap.php         – start każdej strony: ustawienia, sesja, obsługa błędów
    db.php                – połączenie z bazą (PDO)
    session.php           – bezpieczne ustawienia sesji
    csrf.php              – ochrona formularzy tokenem CSRF
    auth.php              – logowanie, wylogowanie, kontrola ról
    validation.php        – sprawdzanie danych z formularzy
    helpers.php           – funkcje pomocnicze (e(), url(), komunikaty)
    panel.php             – dziennik operacji, parametry z adresu, stronicowanie list
    reservations.php      – statusy rezerwacji, historia zmian, filtry, formatowanie dat i cen
    availability.php      – wolne godziny, zapis i przełożenie rezerwacji z blokadą terminu
    /layout               – nagłówek, stopka, menu panelu admina, kalendarz, strona błędu, brak dostępu
/public                   – strony widoczne w przeglądarce
    index.php             – strona główna
    oferta.php            – oferta z kategoriami i wyszukiwarką
    usluga.php            – szczegóły usługi i dostępność pracowników
    rejestracja.php
    logowanie.php
    wyloguj.php
    profil.php            – edycja danych i zmiana hasła
    /klient
        index.php         – moje rezerwacje (nadchodzące, zakończone), anulowanie
        rezerwuj.php      – rezerwacja krok po kroku z kalendarzem
        przeloz.php       – przełożenie wizyty na inny termin
        potwierdzenie.php – potwierdzenie rezerwacji do druku / PDF
        kalendarz.php     – plik .ics do kalendarza w telefonie
    /pracownik
        index.php         – wizyty: dziś, nadchodzące, historia; grafik
        wizyta.php        – szczegóły wizyty i zmiana statusu
    /api
        godziny.php       – wolne godziny w formacie JSON (dla kalendarza, AJAX)
    /admin                – panel administratora:
        index.php         – pulpit ze statystykami i wykresami
        rezerwacje.php    – lista rezerwacji z filtrami
        rezerwacja.php    – szczegóły, zmiana statusu, historia
        eksport.php       – eksport rezerwacji do CSV
        kategorie.php, kategoria.php   – lista i formularz kategorii
        uslugi.php, usluga.php         – lista i formularz usług
        pracownicy.php, pracownik.php  – lista i formularz pracowników (z usługami)
        grafik.php        – godziny pracy i dni wolne pracownika
        uzytkownicy.php   – lista kont, blokowanie
        dziennik.php      – dziennik operacji administratora
    /css
    /js
        formularze.js     – podpowiedzi w formularzach, okienka potwierdzenia
        rezerwacja.js     – wczytywanie wolnych godzin bez przeładowania strony
    /images
/tests
    testy.php             – testy automatyczne (uruchamiane z wiersza poleceń)
```

# Instrukcja uruchomienia

Potrzebny jest XAMPP (albo inny serwer z PHP i MySQL 8.0.16 lub nowszym).

1. Pobierz repozytorium do folderu `htdocs` w XAMPP.
2. W panelu XAMPP włącz **Apache** i **MySQL**.
3. Wejdź na `http://localhost/phpmyadmin`, otwórz zakładkę **Import** i wczytaj plik `database/database.sql`. Baza `swisscar_rezerwacje` utworzy się sama, razem z danymi testowymi. Po każdej zmianie pliku SQL (np. nowe tabele w etapie 3) zaimportuj go ponownie – skrypt usuwa starą bazę i tworzy ją od nowa.
4. Skopiuj plik `config/config.example.php`, nazwij kopię `config.php` i wpisz w niej dane do bazy (w XAMPP zwykle użytkownik `root` bez hasła).
5. Otwórz w przeglądarce: `http://localhost/NAZWA_KATALOGU/` (strona sama przeniesie do folderu `public`).

> Plik `config/config.php` nie trafia na GitHuba (jest w `.gitignore`), więc hasła do bazy nie są publiczne.

> Na hostingu, na którym nie można tworzyć baz poleceniem `CREATE DATABASE` (np. InfinityFree), usuń z początku `database.sql` polecenia `DROP DATABASE`, `CREATE DATABASE` i `USE`, wybierz w phpMyAdmin swoją bazę i dopiero wtedy importuj. Każda tabela ma ustawione kodowanie `utf8mb4_polish_ci`, więc polskie znaki działają także wtedy.

> Ustawienie `debug` w `config.php`: `true` pokazuje szczegóły błędów (tylko podczas pisania kodu), `false` pokazuje użytkownikowi tylko ogólny komunikat.

# Testowe dane logowania

Hasło do wszystkich kont: **`Test123!`**

| Rola | E-mail |
|---|---|
| Administrator | admin@swisscar.pl |
| Pracownik (doradca importowy) | marta.nowak@swisscar.pl |
| Pracownik (specjalista ds. dokumentów) | piotr.wisniewski@swisscar.pl |
| Pracownik (rzeczoznawca) | katarzyna.wojcik@swisscar.pl |
| Klient | jan.zielinski@example.com |
| Klient | ewa.lewandowska@example.com |
| Klient (konto nieaktywne) | robert.mazur@example.com |

Hasła w bazie są zaszyfrowane funkcją `password_hash()`, a nie zapisane zwykłym tekstem.

# Testy automatyczne

W folderze `tests` jest plik z 44 testami najważniejszej logiki: wykrywania kolizji (dokładnie przykład z regulaminu), wyznaczania wolnych godzin, zmian statusu, walidacji danych i zabezpieczeń. Uruchomienie (XAMPP → **Shell**, w folderze projektu):

```
php tests/testy.php
```

Na końcu pojawia się wynik, np. `Wynik: 44 zaliczonych, 0 niezaliczonych`. Plik nie działa z przeglądarki (zwraca 404).

# Konta i logowanie

- **Rejestracja** – formularz z imieniem, nazwiskiem, e-mailem, telefonem, hasłem i powtórzeniem hasła. Nowe konto zawsze dostaje rolę klienta.
- **Logowanie** – e-mailem i hasłem. Po zalogowaniu każda rola trafia do swojego panelu:

| Rola | Panel |
|---|---|
| Klient | `public/klient/index.php` |
| Pracownik | `public/pracownik/index.php` |
| Administrator | `public/admin/index.php` |

- **Powrót po zalogowaniu** – gdy niezalogowana osoba kliknie np. „Zarezerwuj” przy usłudze, po zalogowaniu wraca dokładnie do tej rezerwacji (ale nigdy do panelu innej roli).
- **Limit prób logowania** – po 5 błędnych hasłach do jednego konta (albo 20 z jednego adresu IP) logowanie jest blokowane na 15 minut. Udane logowanie czyści licznik.
- **Wylogowanie** – przyciskiem „Wyloguj” w menu.
- **Profil** – każdy zalogowany użytkownik może zmienić imię, nazwisko, e-mail i telefon oraz zmienić hasło (po podaniu obecnego).

### Zasady sprawdzania danych (po stronie serwera)

| Pole | Zasada |
|---|---|
| Imię, nazwisko | wymagane, od 2 znaków, tylko litery (także polskie), spacja i myślnik |
| E-mail | wymagany, poprawny format, nie może być zajęty przez inne konto |
| Telefon | wymagany, 9–15 cyfr, może zaczynać się od `+` (spacje i myślniki są usuwane) |
| Hasło | min. 8 znaków, co najmniej jedna litera i jedna cyfra |
| Powtórzone hasło | musi być takie samo jak hasło |

Formularz pokazuje błąd pod każdym źle wypełnionym polem i zachowuje wpisane dane (oprócz haseł).

# Bezpieczeństwo

| Zagrożenie | Jak się bronimy | Gdzie w kodzie |
|---|---|---|
| Wyciek haseł z bazy | hasła zapisane jako hash (`password_hash()`), sprawdzane przez `password_verify()` | `auth.php`, `rejestracja.php` |
| SQL injection | każde zapytanie z danymi od użytkownika to prepared statement (PDO) | wszystkie pliki z zapytaniami |
| Wejście do cudzego panelu przez wpisanie adresu | każda chroniona strona zaczyna się od `require_login()` lub `require_role()`; zła rola dostaje stronę „Brak dostępu” (kod 403) | `auth.php` |
| Rejestracja jako administrator | rola nie jest pobierana z formularza, zawsze `klient` | `rejestracja.php` |
| Zmiana cudzych danych w profilu | id użytkownika pochodzi z sesji, nie z formularza | `profil.php` |
| XSS (wstrzyknięcie kodu HTML/JS) | każdy tekst wypisywany na stronie przechodzi przez funkcję `e()` (`htmlspecialchars`) | `helpers.php`, widoki |
| CSRF (wysłanie formularza z obcej strony) | ukryty token w każdym formularzu POST, sprawdzany na serwerze | `csrf.php` |
| Przejęcie sesji | nowy identyfikator sesji po zalogowaniu i zmianie hasła; ciasteczko `HttpOnly` i `SameSite` | `auth.php`, `session.php` |
| Pozostawiony zalogowany komputer | automatyczne wylogowanie po 30 minutach bez aktywności | `auth.php` |
| Zablokowane konto nadal zalogowane | dane użytkownika są sprawdzane w bazie przy każdym wejściu na stronę | `auth.php` |
| Sprawdzanie, czy e-mail ma konto | ten sam komunikat przy złym e-mailu i złym haśle | `auth.php` |
| Ujawnienie szczegółów błędu | użytkownik widzi ogólny komunikat, szczegóły trafiają do logu serwera | `bootstrap.php` |
| Operacje administratora wywołane przez innego użytkownika | każda strona panelu (także zapis formularza i eksport) zaczyna się od `require_role('administrator')` | `public/admin/*.php` |
| Usunięcie lub zmiana nie swojego wpisu przez podmianę id | wpisy grafiku i dni wolnych są usuwane tylko z warunkiem `pracownik_id = ?` | `grafik.php` |
| Utrata historii przez usunięcie danych | usługi z rezerwacjami i kategorie z usługami nie dają się usunąć – można je tylko dezaktywować | `kategorie.php`, `uslugi.php` |
| Formuły w pliku CSV (CSV injection) | wartości zaczynające się od `= + - @` dostają apostrof, więc Excel nie uruchomi ich jako formuły | `eksport.php` |
| Przypadkowe kliknięcie „Usuń” | okienko z pytaniem przed usunięciem, blokadą i anulowaniem | `formularze.js` |
| Zgadywanie haseł (brute force) | blokada na 15 minut po 5 błędnych próbach dla konta lub 20 z jednego IP; próby zapisane w tabeli `proby_logowania` | `auth.php` |
| Rezerwacja zajętego terminu przez zmianę danych formularza | godzina jest sprawdzana na serwerze ponownie tuż przed zapisem: godziny pracy, czas usługi, dni wolne, inne wizyty, przeszłość | `availability.php` |
| Dwie osoby rezerwują ten sam termin w tej samej chwili | zapis w transakcji z blokadą wiersza pracownika (`SELECT … FOR UPDATE`) – druga osoba czeka i dostaje komunikat „termin został przed chwilą zajęty” | `availability.php` |
| Anulowanie / przełożenie / podgląd cudzej rezerwacji | każde zapytanie ma warunek `uzytkownik_id = ?` z sesji; cudza rezerwacja wygląda jak nieistniejąca | `klient/*.php` |
| Pracownik zarządza wizytami innego pracownika | wizyta jest wczytywana tylko z warunkiem `pracownik = zalogowany` | `pracownik/wizyta.php` |
| Klient dubluje swoje wizyty | klient nie może mieć dwóch wizyt nakładających się w czasie, nawet u różnych pracowników | `availability.php` |
| Osadzenie strony w obcej ramce, podmiana typu pliku | nagłówki `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy` | `bootstrap.php` |
| Uruchomienie testów przez internet | plik testów działa tylko z wiersza poleceń | `tests/testy.php` |
| Hasło do bazy na GitHubie | `config.php` jest w `.gitignore` | `.gitignore` |

# Oferta i rezerwacja

### Oferta
- `oferta.php` – lista aktywnych usług (tylko z aktywnych kategorii), filtrowanie po kategorii, wyszukiwarka, stronicowanie.
- `usluga.php` – nazwa, opis, kategoria, cena, czas trwania i dostępność: którzy pracownicy wykonują usługę, ich najbliższy wolny termin i liczba dni z wolnymi godzinami w ciągu 2 tygodni.

### Proces rezerwacji (`klient/rezerwuj.php`)

Zawsze w kolejności: **usługa → pracownik → data → godzina → potwierdzenie**. U góry widać kroki, a do poprzednich można wrócić kliknięciem.

1. **Usługa** – tylko aktywne usługi, które ktoś wykonuje.
2. **Pracownik** – tylko aktywni pracownicy wykonujący wybraną usługę, z najbliższym wolnym terminem.
3. **Data** – graficzny kalendarz na 60 dni: zielone dni mają wolne godziny (liczba w kratce), szare nie.
4. **Godzina** – po kliknięciu dnia wolne godziny wczytują się bez przeładowania strony (AJAX, `api/godziny.php`). Bez JavaScriptu działa to zwykłym linkiem.
5. **Potwierdzenie** – podsumowanie, opcjonalny komentarz (np. VIN) i przycisk „Potwierdzam rezerwację”.

### Jak wyznaczane są wolne godziny (`availability.php`)

Dla wybranego pracownika i dnia:
1. bierzemy jego godziny pracy w ten dzień tygodnia (może być kilka przedziałów, np. z przerwą),
2. jeśli ma w tym dniu dzień wolny – dzień jest niedostępny,
3. sprawdzamy kolejne godziny startu co 15 minut; wizyta musi w całości zmieścić się w godzinach pracy (start + czas trwania usługi ≤ koniec pracy),
4. odrzucamy godziny, które nakładają się na inne wizyty tego pracownika (oprócz anulowanych),
5. dzisiaj odrzucamy godziny, które już minęły.

**Reguła kolizji (pkt 7.1 regulaminu):** dwie wizyty kolidują, gdy `początek_nowej < koniec_istniejącej` i `koniec_nowej > początek_istniejącej`. Dla wizyty 10:00–11:00 blokuje to 9:30–10:30, 10:00–11:00, 10:15–10:45, 10:30–11:30 i 9:30–11:30, a dopuszcza wizyty kończące się o 10:00 i zaczynające o 11:00. Sprawdzają to testy automatyczne.

### Zapis rezerwacji

1. Serwer sprawdza wszystko jeszcze raz (usługa aktywna, pracownik wykonuje usługę, data w zakresie, poprawny format godziny).
2. W transakcji blokuje wiersz pracownika (`SELECT … FOR UPDATE`), więc dwie równoczesne rezerwacje u tej samej osoby wykonują się po kolei.
3. Ponownie wylicza wolne godziny i sprawdza, czy wybrana nadal jest wolna, oraz czy klient nie ma w tym czasie innej wizyty.
4. Zapisuje rezerwację (koniec liczony z czasu usługi, cena z dnia rezerwacji, status „oczekująca”) i wpis w historii statusów.
5. Przy kolizji klient widzi czytelny komunikat i wraca do wyboru godziny – nie ma błędu aplikacji.

# Panel klienta

- **Nadchodzące** i **Zakończone i anulowane** – dla każdej wizyty: usługa, pracownik, data, godzina, cena, status.
- **Anuluj** – tylko własna, przyszła, nieanulowana wizyta (z okienkiem potwierdzenia); zapis w historii statusów.
- **Przełóż** – wybór nowego terminu w tym samym kalendarzu (własna wizyta nie blokuje sama siebie), ta sama cena i długość; wizyta wraca do statusu „oczekująca”.
- **Potwierdzenie** – strona do wydruku lub zapisania jako PDF (przycisk „Drukuj / zapisz jako PDF”).
- **Dodaj do kalendarza** – plik `.ics` z przypomnieniem godzinę przed wizytą (działa w Google Calendar, Outlook, iPhone).

# Panel pracownika

- Zakładki **Dzisiaj**, **Nadchodzące**, **Historia** (ze stronicowaniem) – tylko własne wizyty.
- Licznik wizyt czekających na potwierdzenie.
- **Szczegóły wizyty** – dane klienta (telefon, e-mail), komentarz, historia zmian statusu.
- **Zmiana statusu** – przyciski tylko z dozwolonymi zmianami (np. przyszłej wizyty nie da się oznaczyć jako zrealizowanej).
- **Mój grafik** i **dni wolne** – podgląd (ustawia je administrator).

# Panel administratora

Panel jest dostępny tylko dla roli administratora (`public/admin/`). Menu panelu: Pulpit, Rezerwacje, Kategorie, Usługi, Pracownicy, Użytkownicy, Dziennik operacji.

| Moduł | Co można zrobić |
|---|---|
| **Pulpit** | liczba rezerwacji na dziś i wszystkich, przychód w bieżącym miesiącu, liczba klientów, pracowników i usług; lista wizyt na dziś; wykresy: wizyty w najbliższych 14 dniach, rezerwacje według statusu, najpopularniejsze usługi; ostatnie operacje |
| **Rezerwacje** | lista z filtrowaniem po dacie (od–do), statusie, pracowniku i kliencie; stronicowanie; szczegóły rezerwacji; zmiana statusu i anulowanie; historia zmian statusu; eksport do CSV |
| **Kategorie** | dodawanie, edycja, aktywowanie/dezaktywowanie, usuwanie (tylko pustych), wyszukiwanie |
| **Usługi** | dodawanie, edycja (kategoria, cena, czas trwania, opis), aktywowanie/dezaktywowanie, usuwanie (tylko bez rezerwacji), filtrowanie po kategorii i statusie, wyszukiwanie |
| **Pracownicy** | dodawanie pracownika razem z kontem, edycja danych i stanowiska, przypisywanie wielu usług, aktywowanie/dezaktywowanie, wyszukiwanie |
| **Grafik** | tygodniowe godziny pracy (także kilka przedziałów w jednym dniu, np. z przerwą) oraz dni wolne i urlopy |
| **Użytkownicy** | lista kont z wyszukiwaniem i filtrem roli, blokowanie i odblokowywanie kont |
| **Dziennik operacji** | kto, co i kiedy zmienił w panelu, z wyszukiwaniem |

### Zasady w panelu

| Sytuacja | Co robi system |
|---|---|
| Usunięcie kategorii, do której należą usługi | odmowa z wyjaśnieniem – trzeba przenieść usługi albo dezaktywować kategorię |
| Usunięcie usługi, która ma rezerwacje | odmowa – usługę można dezaktywować (zniknie z oferty, historia zostanie) |
| Zmiana ceny usługi | stare rezerwacje zachowują swoją cenę |
| Dezaktywacja pracownika z umówionymi wizytami | zmiana się udaje, ale pojawia się ostrzeżenie z liczbą wizyt |
| Godziny pracy nachodzące na inne godziny tego samego dnia | odmowa; przedziały stykające się (np. 9:00–12:00 i 12:00–15:00) są dozwolone |
| Dni wolne w przeszłości lub nachodzące na inne dni wolne | odmowa |
| Dni wolne w okresie, w którym są umówione wizyty | zapis się udaje, ale pojawia się ostrzeżenie z liczbą wizyt |
| Zablokowanie własnego konta | odmowa – administrator straciłby dostęp do panelu |
| Oznaczenie przyszłej wizyty jako zrealizowanej | odmowa |
| Przywrócenie anulowanej rezerwacji | odmowa – termin mógł zająć ktoś inny |

Dozwolone zmiany statusu rezerwacji:

| Obecny status | Można zmienić na |
|---|---|
| Oczekująca | Potwierdzona, Zrealizowana, Anulowana |
| Potwierdzona | Oczekująca, Zrealizowana, Anulowana |
| Zrealizowana | Potwierdzona (poprawka pomyłki) |
| Anulowana | – |

### Walidacja formularzy panelu

| Pole | Zasada |
|---|---|
| Nazwa kategorii | od 2 do 100 znaków, unikalna |
| Nazwa usługi | od 2 do 150 znaków, unikalna w obrębie kategorii |
| Czas trwania usługi | liczba całkowita od 5 do 480 minut, co 5 minut |
| Cena | od 0 do 99 999,99 zł, najwyżej 2 miejsca po przecinku (można wpisać przecinek) |
| Kategoria usługi, usługi pracownika | tylko wartości istniejące w bazie (podmienione id są pomijane) |
| Stanowisko | od 2 do 100 znaków |
| Godziny pracy | format GG:MM, koniec później niż początek |
| Dni wolne | poprawne daty, koniec nie wcześniej niż początek, nie w przeszłości |
| Filtry w adresie URL | błędne wartości (np. `status=xyz`, `strona=abc`, data `2026-02-30`) są ignorowane |

# Baza danych

# Diagram ERD

![Diagram ERD](docs/erd.png)

# Tabele

| Tabela | Co przechowuje |
|---|---|
| `uzytkownicy` | konta wszystkich użytkowników (klient, pracownik, administrator) |
| `kategorie_uslug` | kategorie usług |
| `uslugi` | usługi z ceną i czasem trwania |
| `pracownicy` | dodatkowe dane pracownika, połączone z jego kontem |
| `pracownicy_uslugi` | które usługi wykonuje który pracownik |
| `dostepnosc_pracownikow` | godziny pracy pracowników |
| `rezerwacje` | rezerwacje klientów i ich status |
| `dni_wolne` | urlopy i dni wolne pracowników (od–do, powód) |
| `historia_statusow` | każda zmiana statusu rezerwacji: stary i nowy status, kto i kiedy |
| `dziennik_operacji` | operacje wykonane w systemie (panel admina, anulowania i przełożenia klientów, zmiany statusów przez pracowników) |
| `proby_logowania` | nieudane próby logowania (e-mail, adres IP, czas) – do blokady zgadywania haseł |

# Relacje

- **1:N** (jeden do wielu) – kategoria → usługi, użytkownik → rezerwacje, pracownik → rezerwacje, usługa → rezerwacje, pracownik → godziny pracy, pracownik → dni wolne, rezerwacja → historia statusów, użytkownik → wpisy w dzienniku
- **1:1** (jeden do jednego) – konto użytkownika → profil pracownika
- **N:M** (wiele do wielu) – pracownicy ↔ usługi, przez tabelę `pracownicy_uslugi`

# Najważniejsze decyzje projektowe

- **Ceny jako `DECIMAL(8,2)`** – typ `FLOAT` może źle zaokrąglać kwoty, a przy pieniądzach nie może być błędów.
- **Hasło w polu `VARCHAR(255)`** – zaszyfrowane hasło ma teraz ok. 60 znaków, ale w przyszłości może być dłuższe.
- **Cena zapisana w rezerwacji** – gdy administrator zmieni cenę usługi, stare rezerwacje zachowają cenę z dnia rezerwacji.
- **Godzina końca zapisana w rezerwacji** – gdy administrator zmieni czas trwania usługi, stare rezerwacje nie zmienią długości. Dzięki temu łatwo też sprawdzić, czy nowy termin nie nakłada się na inny.
- **Dane nie są usuwane** – usługi, kategorie, pracowników i użytkowników można wyłączyć, a rezerwacje się anuluje. Dzięki temu nie tracimy historii rezerwacji.
- **Usuwanie powiązanych danych** – nie da się usunąć czegoś, do czego są przypisane rezerwacje (`RESTRICT`). Po usunięciu pracownika jego godziny pracy i przypisane usługi usuwają się same (`CASCADE`).
- **Zabezpieczenia w bazie**:
  - `UNIQUE` – nie mogą się powtórzyć: e-mail, nazwa kategorii, nazwa usługi w kategorii, profil pracownika dla tego samego konta, te same godziny pracy,
  - `CHECK` – czas usługi większy od 0 (max 480 min), cena nie mniejsza niż 0, dzień tygodnia od 1 do 7, koniec później niż początek,
  - `ENUM` – można wpisać tylko ustalone role i statusy rezerwacji.
- **Dzień tygodnia 1–7** – 1 to poniedziałek, 7 to niedziela.
- **Indeksy** (przyspieszają wyszukiwanie):
  - `rezerwacje (pracownik_id, data_rezerwacji, godzina_od)` – szukanie zajętych godzin pracownika w danym dniu,
  - `rezerwacje (data_rezerwacji, status)` – filtrowanie rezerwacji w panelu administratora,
  - `uslugi (kategoria_id, aktywna)` – pokazywanie aktywnych usług z wybranej kategorii,
  - `dni_wolne (pracownik_id, data_od, data_do)` – sprawdzanie, czy pracownik ma wolne w danym dniu,
  - `historia_statusow (rezerwacja_id, utworzono)` – historia jednej rezerwacji,
  - `dziennik_operacji (utworzono)` – najnowsze wpisy dziennika,
  - `proby_logowania (email, utworzono)` i `(adres_ip, utworzono)` – liczenie nieudanych prób z ostatnich 15 minut.
- **Strefa czasowa** – po połączeniu z bazą ustawiamy jej strefę czasową na taką samą jak w PHP, żeby daty z bazy i z PHP się zgadzały także na zagranicznym hostingu.
- **Historia i dziennik przy usuwaniu kont** – `ON DELETE SET NULL`: wpis zostaje, tylko bez autora.
- **Zmiana statusu i wpis w historii w jednej transakcji** – nie może się zdarzyć, że status się zmieni, a historia nie.

# Dane testowe

W bazie są: 1 administrator, 3 pracowników z różnymi godzinami pracy (jeden ma przerwę), 5 klientów (jedno konto jest wyłączone), 4 kategorie, 11 usług (jedna wyłączona), 36 rezerwacji z września–grudnia we wszystkich statusach (w tym wizyty w tygodniu prezentacji 23–27.11) (część z historią zmian statusu) oraz dni wolne dwóch pracowników. Dwie rezerwacje są jedna po drugiej (jedna kończy się, a druga zaczyna o 11:00), żeby pokazać, że takie terminy są dozwolone.

# Funkcje dodatkowe

Zrobione:

- **Tokeny CSRF** we wszystkich formularzach.
- **Zmiana hasła** tylko po podaniu obecnego hasła.
- **Automatyczne wylogowanie** po 30 minutach bez aktywności.
- **Natychmiastowa blokada konta** – wyłączony użytkownik jest wylogowywany przy następnym wejściu na stronę.
- **Aktualizacja hashy haseł** – przy logowaniu stary hash jest zamieniany na nowszy, jeśli PHP zmieni algorytm.
- **Wykresy i statystyki** na pulpicie administratora – zwykły HTML i CSS, bez zewnętrznych bibliotek.
- **Historia zmian statusów** każdej rezerwacji (kto i kiedy zmienił).
- **Dziennik operacji administratora** z wyszukiwaniem.
- **Eksport rezerwacji do CSV** z tymi samymi filtrami co lista, z ochroną przed CSV injection.
- **Dni wolne i urlopy pracowników** z ostrzeżeniem o kolidujących wizytach.
- **Kilka przedziałów godzin pracy w jednym dniu** (przerwy).
- **Zaawansowane filtrowanie i stronicowanie** list w panelu.
- **Graficzny kalendarz** dostępnych dni z liczbą wolnych godzin.
- **AJAX** – wolne godziny wczytywane bez przeładowania strony (`api/godziny.php`, JSON).
- **Automatyczne wyznaczanie wolnych godzin** z uwzględnieniem godzin pracy, przerw, dni wolnych, czasu trwania usługi i innych wizyt.
- **Przełożenie wizyty** przez klienta.
- **Obsługa równoczesnej rezerwacji** tego samego terminu (blokada w transakcji).
- **Potwierdzenie rezerwacji do druku / PDF**.
- **Dodanie wizyty do kalendarza** (plik `.ics` z przypomnieniem).
- **Powrót do przerwanej rezerwacji** po zalogowaniu.
- **Limit prób logowania** (ochrona przed zgadywaniem haseł).
- **Nagłówki bezpieczeństwa** HTTP.
- **Testy automatyczne** (`tests/testy.php`, 44 testy).

# Postęp prac

| Etap | Termin | Zakres | Status |
|---|---|---|---|
| 1 | 25.09.2026 | Projekt, GitHub i baza danych | ✅ ukończony |
| 2 | 16.10.2026 | Użytkownicy i logowanie | ✅ ukończony |
| 3 | 06.11.2026 | Panel administracyjny | ✅ ukończony |
| 4 | 20.11.2026 | Klient i rezerwacje | ✅ ukończony |
| 5 | 27.11.2026 | Kompletny projekt i prezentacja | ✅ ukończony |
