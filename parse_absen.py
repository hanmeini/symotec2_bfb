import sys
import xlrd
import json
from datetime import datetime

def parse_excel(filepath):
    try:
        workbook = xlrd.open_workbook(filepath)
    except Exception as e:
        return {"status": "error", "message": f"Gagal membuka file Excel: {str(e)}"}
    
    if not workbook.sheet_names():
        return {"status": "error", "message": "File Excel tidak memiliki sheet."}
    
    sheet = workbook.sheet_by_index(0)
    records = []
    
    for r in range(2, sheet.nrows):  # Skip header rows 0 and 1
        row_vals = [sheet.cell_value(r, c) for c in range(sheet.ncols)]
        
        # Skip if row is empty or summary row
        if not row_vals or not row_vals[0]:
            continue
        if str(row_vals[0]).strip().startswith("Total Personal:"):
            continue
            
        # Parse staff id
        staff_id_str = str(row_vals[1]).strip()
        if not staff_id_str:
            continue
            
        try:
            # Handle float representation (e.g. 1.0 -> 1)
            staff_id = int(float(staff_id_str))
        except ValueError:
            continue
            
        nama = str(row_vals[0]).strip()
        dept_excel = str(row_vals[2]).strip()
        tanggal_str = str(row_vals[3]).strip()
        hari = str(row_vals[4]).strip()
        tipe_hari = str(row_vals[5]).strip()
        jadwal = str(row_vals[6]).strip()
        
        # Parse date to YYYY-MM-DD
        try:
            date_obj = datetime.strptime(tanggal_str, "%d/%m/%Y")
            tanggal = date_obj.strftime("%Y-%m-%d")
        except ValueError:
            tanggal = tanggal_str
            
        # Get actual scans
        scan_in = str(row_vals[8]).strip() if sheet.ncols > 8 else ""
        scan_out = str(row_vals[10]).strip() if sheet.ncols > 10 else ""
        
        # Get machine metrics
        def get_numeric(val, default=0.0):
            if not val or str(val).strip() == "":
                return default
            try:
                return float(val)
            except ValueError:
                return default
                
            
        kerja = get_numeric(row_vals[15]) if sheet.ncols > 15 else 0.0
        lembur = get_numeric(row_vals[16]) if sheet.ncols > 16 else 0.0
        kurang = get_numeric(row_vals[17]) if sheet.ncols > 17 else 0.0
        terlambat = get_numeric(row_vals[18]) if sheet.ncols > 18 else 0.0
        pulang_cepat = get_numeric(row_vals[19]) if sheet.ncols > 19 else 0.0
        absen = get_numeric(row_vals[20]) if sheet.ncols > 20 else 0.0
        lupa_in_out = get_numeric(row_vals[21]) if sheet.ncols > 21 else 0.0
        ijin = get_numeric(row_vals[22]) if sheet.ncols > 22 else 0.0
        alasan_ijin = str(row_vals[23]).strip() if sheet.ncols > 23 else ""
        
        records.append({
            "no_staff": staff_id,
            "nama": nama,
            "dept_excel": dept_excel,
            "tanggal": tanggal,
            "hari": hari,
            "tipe_hari": tipe_hari,
            "jadwal": jadwal,
            "scan_in": scan_in,
            "scan_out": scan_out,
            "kerja": kerja,
            "lembur": lembur,
            "kurang": kurang,
            "terlambat": terlambat,
            "pulang_cepat": pulang_cepat,
            "absen": absen,
            "lupa_in_out": lupa_in_out,
            "ijin": ijin,
            "alasan_ijin": alasan_ijin
        })
        
    return {"status": "success", "data": records}

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "Path file Excel tidak disediakan."}))
        sys.exit(1)
        
    excel_path = sys.argv[1]
    result = parse_excel(excel_path)
    print(json.dumps(result))
