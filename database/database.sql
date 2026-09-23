DROP DATABASE IF EXISTS swisscar_rezerwacje;
CREATE DATABASE swisscar_rezerwacje
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_polish_ci;
USE swisscar_rezerwacje;

SET NAMES utf8mb4;

CREATE TABLE uzytkownicy (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    imie            VARCHAR(50)  NOT NULL,
    nazwisko        VARCHAR(80)  NOT NULL,
    email           VARCHAR(150) NOT NULL,
    haslo_hash      VARCHAR(255) NOT NULL,
    telefon         VARCHAR(20)  NOT NULL,
    rola            ENUM('klient', 'pracownik', 'administrator') NOT NULL DEFAULT 'klient',
    aktywny         TINYINT(1)   NOT NULL DEFAULT 1,
    utworzono       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT uq_uzytkownicy_email UNIQUE (email),
    CONSTRAINT chk_uzytkownicy_aktywny CHECK (aktywny IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE kategorie_uslug (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nazwa    VARCHAR(100) NOT NULL,
    opis     TEXT NULL,
    aktywna  TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT uq_kategorie_nazwa UNIQUE (nazwa),
    CONSTRAINT chk_kategorie_aktywna CHECK (aktywna IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE uslugi (
    id                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    kategoria_id      INT UNSIGNED      NOT NULL,
    nazwa             VARCHAR(150)      NOT NULL,
    opis              TEXT NULL,
    czas_trwania_min  SMALLINT UNSIGNED NOT NULL,
    cena              DECIMAL(8,2)      NOT NULL,
    aktywna           TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT uq_uslugi_kategoria_nazwa UNIQUE (kategoria_id, nazwa),
    CONSTRAINT fk_uslugi_kategoria
        FOREIGN KEY (kategoria_id) REFERENCES kategorie_uslug (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_uslugi_czas_trwania CHECK (czas_trwania_min > 0 AND czas_trwania_min <= 480),
    CONSTRAINT chk_uslugi_cena CHECK (cena >= 0),
    CONSTRAINT chk_uslugi_aktywna CHECK (aktywna IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE pracownicy (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uzytkownik_id  INT UNSIGNED NOT NULL,
    stanowisko     VARCHAR(100) NOT NULL,
    opis           TEXT NULL,
    aktywny        TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    CONSTRAINT uq_pracownicy_uzytkownik UNIQUE (uzytkownik_id),
    CONSTRAINT fk_pracownicy_uzytkownik
        FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_pracownicy_aktywny CHECK (aktywny IN (0, 1))
) ENGINE=InnoDB;

CREATE TABLE pracownicy_uslugi (
    pracownik_id  INT UNSIGNED NOT NULL,
    usluga_id     INT UNSIGNED NOT NULL,
    PRIMARY KEY (pracownik_id, usluga_id),
    CONSTRAINT fk_pracownicy_uslugi_pracownik
        FOREIGN KEY (pracownik_id) REFERENCES pracownicy (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_pracownicy_uslugi_usluga
        FOREIGN KEY (usluga_id) REFERENCES uslugi (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dostepnosc_pracownikow (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    pracownik_id    INT UNSIGNED     NOT NULL,
    dzien_tygodnia  TINYINT UNSIGNED NOT NULL,
    godzina_od      TIME             NOT NULL,
    godzina_do      TIME             NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_dostepnosc UNIQUE (pracownik_id, dzien_tygodnia, godzina_od),
    CONSTRAINT fk_dostepnosc_pracownik
        FOREIGN KEY (pracownik_id) REFERENCES pracownicy (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_dostepnosc_dzien CHECK (dzien_tygodnia BETWEEN 1 AND 7),
    CONSTRAINT chk_dostepnosc_godziny CHECK (godzina_do > godzina_od)
) ENGINE=InnoDB;

CREATE TABLE rezerwacje (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    uzytkownik_id    INT UNSIGNED NOT NULL,
    pracownik_id     INT UNSIGNED NOT NULL,
    usluga_id        INT UNSIGNED NOT NULL,
    data_rezerwacji  DATE         NOT NULL,
    godzina_od       TIME         NOT NULL,
    godzina_do       TIME         NOT NULL,
    cena             DECIMAL(8,2) NOT NULL,
    status           ENUM('oczekujaca', 'potwierdzona', 'zrealizowana', 'anulowana') NOT NULL DEFAULT 'oczekujaca',
    komentarz        VARCHAR(500) NULL,
    utworzono        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zaktualizowano   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_rezerwacje_uzytkownik
        FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rezerwacje_pracownik
        FOREIGN KEY (pracownik_id) REFERENCES pracownicy (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rezerwacje_usluga
        FOREIGN KEY (usluga_id) REFERENCES uslugi (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_rezerwacje_godziny CHECK (godzina_do > godzina_od),
    CONSTRAINT chk_rezerwacje_cena CHECK (cena >= 0)
) ENGINE=InnoDB;

CREATE INDEX idx_rezerwacje_pracownik_data ON rezerwacje (pracownik_id, data_rezerwacji, godzina_od);
CREATE INDEX idx_rezerwacje_data_status ON rezerwacje (data_rezerwacji, status);
CREATE INDEX idx_uslugi_kategoria_aktywna ON uslugi (kategoria_id, aktywna);

INSERT INTO uzytkownicy (id, imie, nazwisko, email, haslo_hash, telefon, rola, aktywny) VALUES
(1, 'Anna',      'Kowalska',    'admin@swisscar.pl',               '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '500100100', 'administrator', 1),
(2, 'Marta',     'Nowak',       'marta.nowak@swisscar.pl',         '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '500200201', 'pracownik',     1),
(3, 'Piotr',     'Wiśniewski',  'piotr.wisniewski@swisscar.pl',    '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '500200202', 'pracownik',     1),
(4, 'Katarzyna', 'Wójcik',      'katarzyna.wojcik@swisscar.pl',    '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '500200203', 'pracownik',     1),
(5, 'Jan',       'Zieliński',   'jan.zielinski@example.com',       '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '600300301', 'klient',        1),
(6, 'Ewa',       'Lewandowska', 'ewa.lewandowska@example.com',     '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '600300302', 'klient',        1),
(7, 'Tomasz',    'Kamiński',    'tomasz.kaminski@example.com',     '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '600300303', 'klient',        1),
(8, 'Agnieszka', 'Dąbrowska',   'agnieszka.dabrowska@example.com', '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '600300304', 'klient',        1),
(9, 'Robert',    'Mazur',       'robert.mazur@example.com',        '$2y$10$gF0NuUaNjIfks/FMob4mWersZ.AkZXjqubG1i.lDAG6l0djzbn79u', '600300305', 'klient',        0);

INSERT INTO kategorie_uslug (id, nazwa, opis, aktywna) VALUES
(1, 'Doradztwo',               'Konsultacje, wyceny i pomoc w wyborze auta ze Szwajcarii.', 1),
(2, 'Weryfikacja pojazdu',     'Sprawdzenie historii, stanu technicznego i diagnostyka auta.', 1),
(3, 'Formalności i dokumenty', 'Odprawa celna, tłumaczenia i rejestracja pojazdu w Polsce.', 1),
(4, 'Odbiór pojazdu',          'Przekazanie sprowadzonego auta i kompletu dokumentów.', 1);

INSERT INTO uslugi (id, kategoria_id, nazwa, opis, czas_trwania_min, cena, aktywna) VALUES
(1,  1, 'Konsultacja importowa',                      'Omówienie procesu sprowadzenia auta, kosztów i terminów.',           60,  150.00, 1),
(2,  1, 'Wycena kosztów sprowadzenia',                'Wyliczenie cła, akcyzy, VAT, transportu i opłat rejestracyjnych.',   30,  100.00, 1),
(3,  1, 'Konsultacja online (wideo)',                 'Krótka rozmowa wideo z doradcą lub rzeczoznawcą.',                   30,   80.00, 1),
(4,  2, 'Weryfikacja historii pojazdu (VIN)',         'Sprawdzenie przebiegu, historii serwisowej i szkód po numerze VIN.', 45,  120.00, 1),
(5,  2, 'Oględziny auta w Szwajcarii (wideo)',        'Oględziny z partnerem na miejscu, z transmisją wideo dla klienta.', 120,  450.00, 1),
(6,  2, 'Diagnostyka po sprowadzeniu',                'Diagnostyka komputerowa i ocena stanu technicznego w Polsce.',       60,  200.00, 1),
(7,  3, 'Przygotowanie dokumentów do odprawy celnej', 'Kompletowanie dokumentów i zgłoszenie celne pojazdu.',               90,  300.00, 1),
(8,  3, 'Tłumaczenie dokumentów szwajcarskich',       'Weryfikacja i zlecenie tłumaczenia przysięgłego dokumentów auta.',   45,  180.00, 1),
(9,  3, 'Pomoc w rejestracji pojazdu',                'Przygotowanie wniosku i dokumentów do wydziału komunikacji.',        60,  250.00, 1),
(10, 4, 'Odbiór pojazdu i przekazanie dokumentów',    'Wydanie auta klientowi i omówienie dokumentów. W cenie importu.',    45,    0.00, 1),
(11, 2, 'Wyjazd na oględziny do Szwajcarii',          'Osobisty wyjazd rzeczoznawcy do sprzedawcy (cały dzień).',          480, 1500.00, 0);

INSERT INTO pracownicy (id, uzytkownik_id, stanowisko, opis, aktywny) VALUES
(1, 2, 'Doradca importowy',          'Pomaga w wyborze auta i wyliczeniu pełnych kosztów sprowadzenia.', 1),
(2, 3, 'Specjalista ds. dokumentów', 'Odprawy celne, tłumaczenia i rejestracja pojazdów w Polsce.', 1),
(3, 4, 'Rzeczoznawca samochodowy',   'Weryfikacja historii i stanu technicznego sprowadzanych aut.', 1);

INSERT INTO pracownicy_uslugi (pracownik_id, usluga_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 10),
(2, 2), (2, 7), (2, 8), (2, 9), (2, 10),
(3, 3), (3, 4), (3, 5), (3, 6), (3, 11);

INSERT INTO dostepnosc_pracownikow (pracownik_id, dzien_tygodnia, godzina_od, godzina_do) VALUES
(1, 1, '09:00', '17:00'), (1, 2, '09:00', '17:00'), (1, 3, '09:00', '17:00'),
(1, 4, '09:00', '17:00'), (1, 5, '09:00', '17:00'),
(2, 2, '10:00', '18:00'), (2, 3, '10:00', '18:00'), (2, 4, '10:00', '18:00'),
(2, 5, '10:00', '18:00'), (2, 6, '10:00', '14:00'),
(3, 1, '12:00', '20:00'), (3, 3, '12:00', '20:00'), (3, 5, '12:00', '20:00'),
(3, 6, '09:00', '12:00'), (3, 6, '12:30', '15:00');

INSERT INTO rezerwacje (uzytkownik_id, pracownik_id, usluga_id, data_rezerwacji, godzina_od, godzina_do, cena, status, komentarz) VALUES
(5, 1, 1,  '2026-09-14', '10:00', '11:00', 150.00, 'zrealizowana', 'Interesuje mnie Audi A6 z 2021 r.'),
(6, 1, 2,  '2026-09-14', '11:00', '11:30', 100.00, 'zrealizowana', 'Skoda Superb, benzyna 2.0.'),
(7, 2, 7,  '2026-09-15', '10:00', '11:30', 300.00, 'zrealizowana', NULL),
(5, 3, 4,  '2026-09-16', '14:00', '14:45', 120.00, 'zrealizowana', 'VIN: WAUZZZF20MN000001'),
(6, 3, 5,  '2026-09-18', '16:00', '18:00', 450.00, 'zrealizowana', 'Auto w Zurychu.'),
(8, 2, 8,  '2026-09-19', '11:00', '11:45', 180.00, 'anulowana',    'Anulowane przez klientkę.'),
(5, 1, 1,  '2026-12-01', '09:00', '10:00', 150.00, 'potwierdzona', NULL),
(6, 1, 10, '2026-12-01', '10:00', '10:45',   0.00, 'oczekujaca',   'Odbiór Skody Superb.'),
(7, 2, 9,  '2026-12-03', '10:00', '11:00', 250.00, 'potwierdzona', NULL),
(5, 2, 2,  '2026-12-03', '11:30', '12:00', 100.00, 'oczekujaca',   NULL),
(6, 3, 6,  '2026-12-04', '13:00', '14:00', 200.00, 'oczekujaca',   'BMW X3 - świeci kontrolka silnika.'),
(7, 3, 3,  '2026-12-05', '09:00', '09:30',  80.00, 'anulowana',    NULL),
(8, 1, 3,  '2026-12-07', '15:00', '15:30',  80.00, 'oczekujaca',   NULL);