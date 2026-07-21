import pandas as pd

file_path = r'c:\Users\X1 CARBON\Downloads\symotec2_bfb\temp\daftar-barang.xlsx'
df = pd.read_excel(file_path)

print("Columns:")
print(df.columns.tolist())

print("\nFirst 3 rows:")
print(df.head(3).to_dict(orient='records'))
