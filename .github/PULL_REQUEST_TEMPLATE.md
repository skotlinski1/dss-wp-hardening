## Opis

Co się zmienia i dlaczego. Wersja `X.Y.Z` i jedno zdanie, dlaczego X, Y albo Z, albo „bez wersji” dla zmian tylko w plikach spoza paczki.

## Jak sprawdzone

Wynik CI na PR i to, co sprawdzono poza nim, np. próbę na WordPressie (z wtyczką i bez niej).

## Rodzaj zmiany

- [ ] Poprawka (hak przestał działać po zmianie w rdzeniu albo działa źle)
- [ ] Nowa możliwość
- [ ] Zmiana łamiąca (strona zaczyna działać inaczej albo musi coś zrobić przy aktualizacji)
- [ ] Bez wersji: tylko dokumentacja, CI albo narzędzia

## Lista kontrolna

- [ ] CI na PR jest zielone
- [ ] `CHANGELOG.md` i dokumentacja opisują nowe lub zmienione zachowanie (tylko stan bieżący)
- [ ] Zmiana haków jest sprawdzona na prawdziwym WordPressie
