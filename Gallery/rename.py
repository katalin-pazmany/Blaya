import os

mappa = r"C:\Users\pkati\Desktop\Blaya\Gallery"
kepek = sorted(
    f for f in os.listdir(mappa)
    if f.lower().endswith((".jpg", ".jpeg", ".png", ".webp"))
)

# 1. lépés: minden kép ideiglenes névre (ütközés kizárva)
temp_nevek = []
for i, fajl in enumerate(kepek, start=1):
    kiterjesztes = os.path.splitext(fajl)[1].lower()
    temp = f"__temp_{i}{kiterjesztes}"
    os.rename(os.path.join(mappa, fajl), os.path.join(mappa, temp))
    temp_nevek.append(temp)

# 2. lépés: ideiglenes névről végleges névre
for i, temp in enumerate(temp_nevek, start=1):
    kiterjesztes = os.path.splitext(temp)[1]
    uj_nev = f"blaya_{i}{kiterjesztes}"
    os.rename(os.path.join(mappa, temp), os.path.join(mappa, uj_nev))
    print(uj_nev)

print(f"Kész! {len(temp_nevek)} kép átnevezve.")