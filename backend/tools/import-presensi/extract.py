"""Ekstrak roster kucing + lokasi dari spreadsheet presensi Jaga Satwa.

Pemakaian: python -I extract.py <ktt.xlsx> <lwt.xlsx> <folder foto-kucing> <out.json>
Struktur sheet: baris 1 = nama kucing (kolom B, D, F, ...), baris 2 = deskripsi di kolom sebelah kanan nama,
baris berikutnya = presensi feeding per tanggal (Done / Not yet).
"""
import sys, json, re, hashlib, os, datetime
sys.stdout.reconfigure(encoding="utf-8")
from openpyxl import load_workbook

ktt, lwt, foto_dir, out = sys.argv[1:5]

import io
from PIL import Image, ImageOps


def sig(data):
    im = ImageOps.exif_transpose(Image.open(io.BytesIO(data))).convert('L').resize((32, 32))
    px = list(im.tobytes())
    mean = sum(px) / len(px)
    return [v - mean for v in px], im.size


foto_sig = {}
foto_hash = {}
for fn in sorted(os.listdir(foto_dir)):
    with open(os.path.join(foto_dir, fn), 'rb') as f:
        b = f.read()
    foto_hash.setdefault(hashlib.sha1(b).hexdigest(), []).append(fn)
    foto_sig[fn] = sig(b)[0]


def best_match(data):
    s = sig(data)[0]
    scored = sorted((sum(abs(a - b) for a, b in zip(s, fs)) / len(s), fn) for fn, fs in foto_sig.items())
    return scored[0], scored[1]

STATUS_MARK = {'HILANG': 'Hilang', 'ADOPTED': 'Diadopsi', 'ADOPSI': 'Diadopsi', 'MATI': 'Almarhum', 'ALMARHUM': 'Almarhum', 'RIP': 'Almarhum'}


def norm(v):
    return re.sub(r'\s+', ' ', str(v)).strip() if v is not None else ''


def parse_name(raw):
    notes = re.findall(r'\(([^)]*)\)', raw)
    name = re.sub(r'\([^)]*\)', '', raw).strip()
    status, other = None, []
    for n in notes:
        key = n.strip().upper()
        if key in STATUS_MARK:
            status = STATUS_MARK[key]
        else:
            other.append(n.strip())
    return name.title(), status, other


def parse_desc(raw):
    gender = steril = None
    ciri, area = [], []
    if raw is None:
        return gender, steril, '', area, ''
    text = str(raw)
    parts = [p.strip() for p in re.split(r'\n|(?=\b\d\.\s)', text) if p.strip()]
    for p in parts:
        body = re.sub(r'^\d+\.\s*', '', p).strip()
        for a in re.findall(r'\(([^)]*)\)', body):
            area.append(a.strip())
        clean = re.sub(r'\([^)]*\)', '', body).strip()
        low = clean.lower()
        if gender is None and re.match(r'^(betina|perempuan|jantan|laki-laki|laki laki|cowok|cewek)\b', low):
            gender = 'Betina' if re.match(r'^(betina|perempuan|cewek)', low) else 'Jantan'
            continue
        if steril is None and re.search(r'\bsteril\b', low) and len(low) <= 20:
            steril = 'Belum' if re.search(r'belum|belom|blm|tidak', low) else 'Sudah'
            continue
        if clean:
            ciri.append(clean)
    return gender, steril, '; '.join(ciri), area, text.strip()


def is_date_cell(v):
    return isinstance(v, (datetime.datetime, datetime.date)) or (isinstance(v, (int, float)) and 1 <= v <= 31)


def scan(path, kampus):
    wb = load_workbook(path, data_only=True)
    result = []
    for ws in wb.worksheets:
        if ws.title.lower().startswith('copy of'):
            result.append({'lokasi': ws.title, 'skip': 'sheet salinan'})
            continue
        cats, seen = [], {}
        for col in range(2, ws.max_column + 1):
            raw = norm(ws.cell(1, col).value)
            if not raw or raw in ('-', 'NAMA', 'TANGGAL'):
                continue
            desc_raw = ws.cell(2, col + 1).value or ws.cell(2, col).value
            name, status, notes = parse_name(raw)
            if not name:
                continue
            key = name.upper()
            if key in seen:
                seen[key]['duplikat_kolom'].append(col)
                continue
            g, s, ciri, area, desc_text = parse_desc(desc_raw)
            done = sum(1 for r in range(3, ws.max_row + 1) if norm(ws.cell(r, col).value).lower() == 'done')
            notyet = sum(1 for r in range(3, ws.max_row + 1) if norm(ws.cell(r, col).value).lower() in ('not yet', 'notyet'))
            cat = {'nama_raw': raw, 'nama': name, 'status_mark': status, 'catatan_nama': notes,
                   'jenis_kelamin': g, 'status_steril': s, 'ciri': ciri, 'area': area, 'desc_raw': desc_text,
                   'col': col, 'duplikat_kolom': [], 'presensi_done': done, 'presensi_notyet': notyet, 'foto': []}
            seen[key] = cat
            cats.append(cat)
        # gambar tertanam -> kucing berdasarkan kolom anchor
        unmatched_imgs = []
        for img in getattr(ws, '_images', []):
            try:
                c0 = img.anchor._from.col + 1
                r0 = img.anchor._from.row + 1
            except AttributeError:
                continue
            data = img._data()
            h = hashlib.sha1(data).hexdigest()
            owner = None
            for cat in cats:
                if c0 in (cat['col'], cat['col'] + 1):
                    owner = cat
                    break
            (d1, f1), (d2, f2) = best_match(data)
            info = {'sha1': h, 'file_cocok': [f1] if d1 < 12 else [], 'skor': round(d1, 2), 'kandidat2': [f2, round(d2, 2)],
                    'anchor': f'{c0}:{r0}', 'bytes': len(data)}
            (owner['foto'] if owner else unmatched_imgs).append(info)
        result.append({'lokasi': ws.title, 'cats': cats, 'gambar_tanpa_pemilik': unmatched_imgs})
    return {'kampus': kampus, 'sheets': result}


data = [scan(ktt, 'Kampus Ketintang'), scan(lwt, 'Kampus Lidah Wetan')]
with open(out, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=1, default=str)

# ringkasan
used = set()
for k in data:
    total = sum(len(s.get('cats', [])) for s in k['sheets'])
    print(f"\n######## {k['kampus']}: {len([s for s in k['sheets'] if 'cats' in s])} lokasi, {total} kucing")
    for s in k['sheets']:
        if 'skip' in s:
            print(f"  [skip] {s['lokasi']} ({s['skip']})")
            continue
        print(f"  == {s['lokasi']} ({len(s['cats'])} kucing, gambar tanpa pemilik: {[ (g['anchor'], g['file_cocok'], g['skor']) for g in s['gambar_tanpa_pemilik']]})")
        for g in s['gambar_tanpa_pemilik']: used.update(g['file_cocok'])
        for c in s['cats']:
            files = [f"{f}@{fo['skor']}" for fo in c['foto'] for f in fo['file_cocok']] + [f"?{fo['kandidat2'][0] if not fo['file_cocok'] else ''}@{fo['skor']}" for fo in c['foto'] if not fo['file_cocok']]
            used.update(f for fo in c['foto'] for f in fo['file_cocok'])
            flag = []
            if not c['jenis_kelamin']: flag.append('GENDER?')
            if not c['status_steril']: flag.append('STERIL?')
            if c['duplikat_kolom']: flag.append(f"dup{c['duplikat_kolom']}")
            print(f"     - {c['nama']:<18} {str(c['jenis_kelamin']):<7} steril={str(c['status_steril']):<6} status={str(c['status_mark']):<9} "
                  f"img={len(c['foto'])} file={files} area={c['area']} notes={c['catatan_nama']} done={c['presensi_done']} {' '.join(flag)}")
            print(f"         ciri: {c['ciri'][:110]}")
all_files = sorted(f for v in foto_hash.values() for f in v)
print(f"\nfoto folder: {len(all_files)} file, terpetakan: {len(used)}, tidak terpetakan: {sorted(set(all_files) - used)}")
