<?php

// Language definitions for frequently used strings
$lang_common = array(

// Text orientation and encoding
'lang_direction'			=>	'ltr',	// ltr (Left-To-Right) or rtl (Right-To-Left)
'lang_identifier'			=>	'pl',

// Number formatting
'lang_decimal_point'	=>	'.',
'lang_thousands_sep'	=>	',',

// Notices
'Bad request'				=>	'Błędny adres. Podany link jest nieprawidłowy, nieaktualny lub nie masz uprawnień do tej strony.',
'No view'					=>	'Nie masz uprawnień, aby przeglądać to forum.',
'No topic view'                                       =>      'Nie masz uprawnie�^�, aby przegl�^�da�^� ten temat.',
'No permission'				=>	'Nie masz uprawnień do tej strony.',
'CSRF token mismatch'		=>	'Nie można potwierdzić kodu zabezpieczającego. Ten błąd następuje wtedy, kiedy upłynie trochę czasu od momentu kiedy otworzyłeś stronę do chwili kiedy wysłałeś formularz, lub kliknąłeś link. Jeśli tak było i chciałbyś potwierdzić swoje działanie, kliknij przycisk Potwierdź. W przeciwnym wypadku, powinieneś kliknąć przycisk Anuluj, aby wrócić na stronę, którą ostatnio odwiedziłeś.',
'No cookie'					=>	'Zalogowałeś się prawidłowo, jednak ciasteczka w przeglądarce nie zostały zapisane. Sprawdź w preferencjach czy ciasteczka są włączone.',


// Miscellaneous
'Bitcoin price'				=>	'Kurs Bitcoina %s $ / %s zł',
'Forum index'				=>	'Strona główna',
'Submit'					=>	'Wyślij',	// "name" of submit buttons
'Cancel'					=>	'Anuluj', // "name" of cancel buttons
'Preview'					=>	'Podgląd',	// submit button to preview message
'Delete'					=>	'Usuń',
'Split'						=>	'Podziel',
'Ban message'				=>	'Zostałeś zbanowany na tym forum.',
'Ban message 2'				=>	'Ban wygasa na końcu %s.',
'Ban message 3'				=>	'Administrator lub moderator, który Cię zbanował zostawił Ci następującą wiadomość:',
'Ban message 4'				=>	'Proszę kierować bezpośrednie prośby do administratora: %s.',
'Never'						=>	'Nigdy',
'Today'						=>	'Dzisiaj',
'Yesterday'					=>	'Wczoraj',
'Forum message'				=>	'Wiadomość forum',
'Maintenance warning'		=>	'<strong>UWAGA! Włączono %s.</strong> NIE WYLOGOWUJ SIĘ, ponieważ nie będziesz miał możliwości zalogowania ponownie.',
'Maintenance mode'			=>	'Tryb konserwacji',
'Redirecting'				=>	'Przekierowanie',
'Forwarding info'			=>	'Powinieneś zostać przekierowany w ciągu %s %s.',
'second'					=>	'sekundy',	// singular
'seconds'					=>	'sekund',	// plural
'Click redirect'			=>	'Kliknij tutaj jeśli nie chcesz czekać dłużej (lub Twoja przeglądarka nie przekierowuje Cię automatycznie)',
'Invalid e-mail'			=>	'Podany adres e-mail jest nieprawidłowy.',
'New posts'					=>	'Nowe posty',	// the link that leads to the first new post
'New posts title'			=>	'Znajdź tematy zawierające nowe posty od Twojej ostatniej wizyty.',	// the popup text for new posts links
'Active topics'				=>	'Aktywne tematy',
'Active topics title'		=>	'Znajdź tematy, które zawierają świeże posty.',
'Unanswered topics'			=>	'Tematy bez odpowiedzi',
'Unanswered topics title'	=>	'Znajdź tematy, które nie mają odpowiedzi',
'Username'					=>	'Nazwa użytkownika',
'Public Key'			=>	'Klucz Publiczy',
'Registered'				=>	'Zarejestrowany',
'Write message'				=>	'Napisz wiadomość:',
'Forum'						=>	'Forum',
'Posts'						=>	'Posty',
'Pages'						=>	'Strony',
'Page'						=>	'Strona',
'BBCode'					=>	'BBCode',	// You probably shouldn't change this
'Smilies'					=>	'Emotikonki',
'Invites'					=>	'Zaproszenia',
'Images'					=>	'Obrazki',
'You may use'				=>	'Możesz używać: %s',
'and'						=>	'i',
'Image link'				=>	'obrazek',	// This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
'wrote'						=>	'napisał/a',	// For [quote]'s (e.g., User wrote:)
'Code'						=>	'Kod',		// For [code]'s
'Forum mailer'				=>	'%s ',	// As in "MyForums Mailer" in the signature of outgoing e-mails
'Write message legend'		=>	'Napisz post',
'Required information'		=>	'Wymagana informacja',
'Reqmark'					=>	'*',
'Required'					=>	'(Wymagane)',
'Required warn'				=>	'Wszystkie pola oznaczone %s muszą zostać wypełnione.',
'Crumb separator'			=>	' »&#160;', // The character or text that separates links in breadcrumbs
'Title separator'			=>	' - ',
'Page separator'			=>	'&#160;', //The character or text that separates page numbers
'Spacer'					=>	'…', // Ellipsis for paginate
'Paging separator'			=>	' ', //The character or text that separates page numbers for page navigation generally
'Previous'					=>	'Poprzednia',
'Next'						=>	'Następna',
'Cancel redirect'			=>	'Operacja anulowana. Trwa przekierowanie…',
'No confirm redirect'		=>	'Nie potwierdzono. Operacja anulowana. Trwa przekierowanie…',
'Please confirm'			=>	'Prosze potwierdź:',
'Help page'					=>	'Pomoc z: %s',
'Re'						=>	'Odp:',
'Page info'					=>	'(Strona %1$s z %2$s)',
'Item info single'			=>	'%s [ %s ]',
'Item info plural'			=>	'%s [ %s do %s z %s ]', // e.g. Topics [ 10 to 20 of 30 ]
'Info separator'			=>	' ', // e.g. 1 Page | 10 Topics
'Powered by'				=>	'Forum oparte o: <strong>%s</strong>',
'Maintenance'				=>	'Zarządzanie',

// CSRF confirmation form
'Confirm'					=>	'Potwierdź',	// Button
'Confirm action'			=>	'Potwierdź działanie',
'Confirm action head'		=>	'Potwierdź lub anuluj poprzednie działanie',

// Title
'Title'						=>	'Tytuł',
'Member'					=>	'Użytkownik',	// Default title
'Moderator'					=>	'Moderator',
'Administrator'				=>	'Administrator',
'Banned'					=>	'Zbanowany',
'Guest'						=>	'Gość',

// Stuff for include/parser.php
'BBCode error 1'			=>	'[/%1$s] został znaleziony bez [%1$s]',
'BBCode error 2'			=>	'[%s] tag jest pusty',
'BBCode error 3'			=>	'[%1$s] został otwarty w [%2$s], to jest niedozwolone',
'BBCode error 4'			=>	'[%s] został otwarty w środku, to jest niedozwolone',
'BBCode error 5'			=>	'[%1$s] został znaleziony bez [/%1$s]',
'BBCode error 6'			=>	'[%s] tag bez wymaganych atrybutów',
'BBCode nested list'		=>	'Tagi [list] nie mogą zostać zagnieżdżone.',
'BBCode code problem'		=>	'Problem z tagiem [code]',

// Stuff for the navigator (top of every page)
'Index'						=>	'Forum',
'User list'					=>	'Użytkownicy',
'Rules'						=>  'Regulamin',
'Search'					=>  'Szukaj',
'Register'					=>  'Rejestracja',
'register'					=>	'zarejestruj',
'Login'						=>  'Logowanie',
'login'						=>	'Zaloguj się',
'Not logged in'				=>  'Nie jesteś zalogowany.',
'Profile'					=>	'Profil',
'Logout'					=>	'Wyloguj',
'Logged in as'				=>	'Zalogowany jako %s.',
'Admin'						=>	'Administracja',
'Last visit'				=>	'Ostatnia wizyta %s',
'Mark all as read'			=>	'Oznacz wszystkie tematy jako przeczytane',
'Login nag'					=>	'Proszę się zalogować lub zarejestrować.',
'New reports'				=>	'Nowe raporty',

// Alerts
'New alerts'				=>	'Nowe uwagi',
'Maintenance alert'			=>	'<strong>UWAGA! Modernizacja.</strong> Forum w modernizacji. <em>NIE</em> wylogowuj się, jeżeli tego dokonasz nie będziesz mógł się zalogować.',
'Updates'					=>	'PunBB aktualizacje:',
'Updates failed'			=>	'Ostatnie sprawdzanie aktualizacji dla punbb.informer.com nie powiodło się. Prawdopodobnie oznacza to, że serwis ten jest tymczasowo niedostępny. Jeśli ta informacja nie zniknie w przeciągu jednego lub dwóch dni, powinieneś wyłączyć funkcję automatycznego sprawdzania aktualizacji.',
'Updates version n hf'		=>	'Nowsza wersja PunBB - %s - jest dostępna do pobrania na <a href="http://punbb.informer.com/">punbb.informer.com</a>. Ponadto, jedna lub więcej poprawek jest dostępnych do instalacji na zakładce <a href="%s">Poprawki</a> w panelu Administracyjnym.',
'Updates version'			=>	'Nowsza wersja PunBB - %s - jest dostępna do pobrania na <a href="http://punbb.informer.com/">punbb.informer.com</a>.',
'Updates hf'				=>	'Jedna lub więcej poprawek jest dostępnych do instalacji na zakładce <a href="%s">Poprawki</a> w panelu Administracyjnym.',
'Database mismatch'			=>	'Wersje bazy danych różnią się',
'Database mismatch alert'	=>	'Baza twojego forum używa starszej wersji, niż wskazuje na to kod PunBB. Oznacza, to że forum może nie funkcjonować poprawnie. Powinieneś zaktualizować go do najnowszej wersji PunBB.',

// Stuff for Jump Menu
'Go'						=>	'Idź',		// submit button in forum jump
'Jump to'					=>	'Idź do forum:',

// For extern.php RSS feed
'ATOM Feed'					=>	'Atom',
'RSS Feed'					=>	'RSS',
'RSS description'			=>	'Najświeże tematy w %s.',
'RSS description topic'		=>	'Najświeższe odpowiedzi w %s.',
'RSS reply'					=>	'Odp: ',	// The topic subject will be appended to this string (to signify a reply)

// Accessibility
'Skip to content'					=>	'Przejdź do treści forum',

// Debug information
'Querytime'						=>	'Wygenerowano w %1$s sekund, wykonano %2$s zapytań',
'Debug table'						=>	'Informacje debugowania',
'Debug summary'						=>	'Informacje o szybkości wykonywania zapytań',
'Query times'						=>	'Czas (s)',
'Query'							=>	'Zapytanie',
'Total query time'					=>	'Czas zapytań łącznie',

);
