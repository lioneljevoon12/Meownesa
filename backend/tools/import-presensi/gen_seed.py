"""Bangun data seed aktual (JSON + foto) dari roster.json hasil extract.py.

python -I gen_seed.py <roster.json> <ktt.xlsx> <lwt.xlsx> <folder foto-kucing> <backend/sources>
"""
import sys, json, re, os, io, datetime
sys.stdout.reconfigure(encoding='utf-8')
from openpyxl import load_workbook
from PIL import Image, ImageOps

roster_path, ktt, lwt, foto_dir, backend = sys.argv[1:6]
roster = json.load(open(roster_path, encoding='utf-8'))
img_out = os.path.join(backend, 'public', 'images', 'kucing')
os.makedirs(img_out, exist_ok=True)

PREFIX = {'Kampus Ketintang': 'KTT', 'Kampus Lidah Wetan': 'LWT'}
# Titik tengah kampus (perkiraan) — DIPAKAI SEMENTARA untuk semua lokasi sampai admin mengoreksi.
PUSAT = {'Kampus Ketintang': (-7.31290000, 112.72750000), 'Kampus Lidah Wetan': (-7.30150000, 112.67400000)}
NAMA_LOKASI = {
    'FISIPOL': 'FISIPOL', 'FISIPOL perbatasan FEB': 'FISIPOL (Perbatasan FEB)', 'FEB': 'FEB',
    'HUKUM': 'Fakultas Hukum', 'LAPANGAN': 'Lapangan', 'FC': 'FC', 'MIKO': 'Area Miko', 'DANAU': 'Danau',
    'DANAU DEPAN MASJID': 'Danau Depan Masjid', 'MASJID SAMPING BIOLOGI': 'Masjid Samping Biologi',
    'DANAU Depan FMIPA': 'Danau Depan FMIPA', 'TEKNIK ATM': 'Teknik (ATM)', 'FISIKA': 'Fisika',
    'TEKNIK SAINS DATA': 'Teknik Sains Data', 'SEKRE TEKNIK': 'Sekretariat Teknik', 'SAMPING ELEKTRO': 'Samping Elektro',
    'PKK': 'PKK', 'TEKNIK MESIN': 'Teknik Mesin', 'GERBANG BELAKANG': 'Gerbang Belakang',
    'DEPAN GED. IDB': 'Depan Gedung IDB', 'JOGLO KIMIA': 'Joglo Kimia', 'UNESAPRESS': 'Unesa Press',
    'FIP': 'FIP', 'FBS': 'FBS', 'LABMER': 'Labmer', 'FIKK': 'FIKK', 'LAPANGAN KUDA': 'Lapangan Kuda', 'REKTORAT': 'Rektorat',
}
EMOJI_ALMARHUM = ('🕊',)
PRIVAT = re.compile(r'\bkos\b|\brumah\b|\bjl\.?\b|\balamat\b', re.I)  # catatan yang menunjuk tempat tinggal orang


def parse_desc(raw):
    """Baris 1 = jenis kelamin (+ catatan dalam kurung), baris 'Steril' = status steril, sisanya = ciri."""
    gender = steril = usia = None
    ciri, notes = [], []
    lines = [re.sub(r'^\d+\.\s*', '', l).strip() for l in str(raw or '').split('\n') if l.strip()]
    for i, line in enumerate(lines):
        low = line.lower()
        m = re.match(r'^(betina|perempuan|jantan|laki-laki|laki laki)\b\s*(?:\((.*)\))?', low)
        if gender is None and m:
            gender = 'Betina' if m.group(1) in ('betina', 'perempuan') else 'Jantan'
            inner = re.search(r'\((.*)\)', line)
            if inner:
                for part in [p.strip() for p in inner.group(1).split(',') if p.strip()]:
                    if part.lower() == 'steril':
                        steril = 'Sudah'
                    elif part.lower() in ('belum steril', 'belom steril'):
                        steril = 'Belum'
                    else:
                        notes.append(part)
            continue
        if steril is None and re.fullmatch(r'(belum|belom)?\s*steril', low):
            steril = 'Belum' if low.startswith(('belum', 'belom')) else 'Sudah'
            continue
        if re.search(r'masih kecil|kitten', low):
            usia = 'Kitten'
        ciri.append(line)
    return gender, steril, usia, ciri, notes


def save_photo(data, kode):
    im = ImageOps.exif_transpose(Image.open(io.BytesIO(data))).convert('RGB')
    im.thumbnail((640, 640))
    rel = f'images/kucing/{kode}.jpg'
    im.save(os.path.join(backend, 'public', rel), 'JPEG', quality=85, optimize=True)
    return rel


# gambar tertanam (untuk kucing yang belum punya file di folder foto-kucing)
embedded = {}
for path, kampus in ((ktt, 'Kampus Ketintang'), (lwt, 'Kampus Lidah Wetan')):
    wb = load_workbook(path)
    for ws in wb.worksheets:
        for img in getattr(ws, '_images', []):
            embedded[(kampus, ws.title, img.anchor._from.col + 1)] = img._data()

out = {
    'sumber': {
        'dokumen': ['Presensi Ketintang 2026.xlsx', 'PRESENSI LIDAH WETAN.xlsx', 'documentations/sources-images/foto-kucing'],
        'diekstrak': datetime.date.today().isoformat(),
        'catatan_koordinat': 'Koordinat semua lokasi = titik tengah kampus (sementara). Koreksi lewat PUT /api/admin/lokasi/{id}.',
    },
    'kampus': [], 'dikecualikan': [], 'catatan_privat_dihapus': [],
}
stat = {'foto_folder': 0, 'foto_spreadsheet': 0, 'tanpa_foto': 0}

for k in roster:
    kampus = k['kampus']
    pre = PREFIX[kampus]
    n = 0
    entry = {'nama_kampus': kampus, 'koordinat_default': PUSAT[kampus], 'lokasi': []}
    for s in k['sheets']:
        if 'skip' in s:
            continue
        lok = {'nama_lokasi': NAMA_LOKASI.get(s['lokasi'], s['lokasi'].title()), 'sheet': s['lokasi'], 'kucing': []}
        for c in s['cats']:
            gender, steril, usia, ciri, notes = parse_desc(c['desc_raw'])
            if not gender:
                out['dikecualikan'].append({'kampus': kampus, 'sheet': s['lokasi'], 'nama_di_sheet': c['nama_raw'],
                                            'alasan': 'Tidak ada deskripsi (jenis kelamin/steril) di spreadsheet'})
                continue
            status = c['status_mark'] or 'Terdata'
            if any(e in c['nama_raw'] for e in EMOJI_ALMARHUM):
                status = 'Almarhum'
            for nt in c['catatan_nama']:
                if PRIVAT.search(nt):
                    out['catatan_privat_dihapus'].append({'kucing': c['nama'], 'catatan': '[disembunyikan]', 'alasan': 'menunjuk tempat tinggal orang'})
            n += 1
            kode = f'{pre}-{n:03d}'
            desk = '. '.join(x.rstrip('.') for x in ciri)
            if notes:
                desk = (desk + '. ' if desk else '') + 'Catatan: ' + ', '.join(notes)
            foto, sumber_foto = None, None
            matched = [f for fo in c['foto'] for f in fo['file_cocok']]
            if matched:
                with open(os.path.join(foto_dir, matched[0]), 'rb') as fh:
                    foto = save_photo(fh.read(), kode)
                sumber_foto = f'foto-kucing/{matched[0]}'
                stat['foto_folder'] += 1
            else:
                data = embedded.get((kampus, s['lokasi'], c['col'])) or embedded.get((kampus, s['lokasi'], c['col'] + 1))
                if data:
                    foto = save_photo(data, kode)
                    sumber_foto = 'spreadsheet (gambar tertanam)'
                    stat['foto_spreadsheet'] += 1
                else:
                    stat['tanpa_foto'] += 1
            lok['kucing'].append({
                'kode_kucing': kode, 'nama_kucing': c['nama'], 'jenis_kelamin': gender, 'kelompok_usia': usia,
                'status_steril': steril or 'Tidak Diketahui', 'status_kucing': status,
                'deskripsi': desk or None, 'foto': foto, 'sumber_foto': sumber_foto,
            })
        entry['lokasi'].append(lok)
    out['kampus'].append(entry)

dest = os.path.join(backend, 'database', 'seeders', 'data')
os.makedirs(dest, exist_ok=True)
with open(os.path.join(dest, 'kucing_aktual.json'), 'w', encoding='utf-8') as f:
    json.dump(out, f, ensure_ascii=False, indent=2)

total = sum(len(l['kucing']) for kk in out['kampus'] for l in kk['lokasi'])
from collections import Counter
cnt = Counter(c['status_kucing'] for kk in out['kampus'] for l in kk['lokasi'] for c in l['kucing'])
ster = Counter(c['status_steril'] for kk in out['kampus'] for l in kk['lokasi'] for c in l['kucing'])
for kk in out['kampus']:
    print(kk['nama_kampus'], len(kk['lokasi']), 'lokasi,', sum(len(l['kucing']) for l in kk['lokasi']), 'kucing')
print('total kucing', total, '| status', dict(cnt), '| steril', dict(ster), '| foto', stat)
print('dikecualikan', len(out['dikecualikan']), [d['nama_di_sheet'] for d in out['dikecualikan']])
print('catatan privat dihapus', out['catatan_privat_dihapus'])
