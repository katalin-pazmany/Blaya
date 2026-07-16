# -*- coding: utf-8 -*-
"""
BLAYA – Galéria lista generáló
─────────────────────────────────────────────────────────────
MIT CSINÁL:
Végignézi a Gallery mappát, összegyűjti az összes kép- és
videófájlt, és elkészíti/frissíti a Gallery/gallery.json fájlt.
A weboldal ezt a fájlt tölti be automatikusan, amikor megnyitod
a Galéria oldalt — így elég csak ezt a scriptet futtatni minden
alkalommal, amikor új képet/videót adsz a mappához vagy törölsz
belőle. Az index.html-hez nem kell hozzányúlni.

HASZNÁLAT:
1. Tedd az új képeket/videókat a Gallery mappába (bármilyen névvel)
2. Futtasd ezt a scriptet (dupla katt vagy python generate_gallery_manifest.py)
3. Töltsd fel a TELJES Gallery mappát (a gallery.json fájllal együtt)
   a Netlify-ra / a weboldal mellé

FONTOS: a gallery.json csak élesben (https://blaya.hu) töltődik be
megbízhatóan. Ha a fájlt a saját gépeden, dupla kattintással nyitod
meg (file:///...), a böngésző biztonsági okból nem engedi beolvasni
– ilyenkor a galéria a régi, beépített listát mutatja tartalékként.
─────────────────────────────────────────────────────────────
"""
import os
import json
import re

# Írd át, ha máshol van a Gallery mappád:
mappa = r"C:\Users\pkati\Desktop\Blaya\Gallery"

KEP_KITERJESZTESEK = (".jpg", ".jpeg", ".png", ".webp", ".gif")
VIDEO_KITERJESZTESEK = (".mp4", ".webm", ".mov", ".m4v", ".ogg")
TAMOGATOTT = KEP_KITERJESZTESEK + VIDEO_KITERJESZTESEK


def termeszetes_rendezokulcs(nev):
    """Úgy rendez, hogy blaya_2 a blaya_10 elé kerüljön (ne ábécé szerint)."""
    return [int(resz) if resz.isdigit() else resz.lower()
            for resz in re.split(r'(\d+)', nev)]


def main():
    if not os.path.isdir(mappa):
        print(f"HIBA: nem található ez a mappa: {mappa}")
        print("Nyisd meg a scriptet szerkesztőben és írd át a 'mappa' változót a helyes útvonalra.")
        return

    fajlok = [
        f for f in os.listdir(mappa)
        if f.lower().endswith(TAMOGATOTT) and f.lower() != "gallery.json"
    ]
    fajlok.sort(key=termeszetes_rendezokulcs)

    if not fajlok:
        print("Nem található kép vagy videó a mappában. Ellenőrizd az útvonalat és a fájlkiterjesztéseket.")
        return

    kimenet = os.path.join(mappa, "gallery.json")
    with open(kimenet, "w", encoding="utf-8") as f:
        json.dump(fajlok, f, ensure_ascii=False, indent=2)

    kepek = sum(1 for f in fajlok if f.lower().endswith(KEP_KITERJESZTESEK))
    videok = sum(1 for f in fajlok if f.lower().endswith(VIDEO_KITERJESZTESEK))

    print(f"Kész! {len(fajlok)} fájl mentve a gallery.json-ba ({kepek} kép, {videok} videó).")
    print("\nFájlok sorrendben:")
    for f in fajlok:
        jelzo = "🎬" if f.lower().endswith(VIDEO_KITERJESZTESEK) else "🖼️"
        print(f"  {jelzo} {f}")
    print(f"\nMentve ide: {kimenet}")
    print("Most töltsd fel a teljes Gallery mappát (gallery.json-nal együtt) a Netlify-ra.")


if __name__ == "__main__":
    main()
