# MOM Writer

เว็บสำหรับเขียนบันทึกการประชุม (Minutes of Meeting) ของ TG Team แล้ว Export เป็น PDF หรือสร้างร่างอีเมลส่งให้ผู้เข้าร่วมประชุม

เป็นเว็บแบบ static ไม่มี backend และ**ไม่เก็บข้อมูลใด ๆ** ข้อมูลอยู่ในหน่วยความจำของหน้าเว็บเท่านั้น ปิดหรือรีเฟรชหน้าแล้วข้อมูลหาย ผู้ใช้ต้อง Export PDF หรือร่างอีเมลก่อนปิดหน้า (หน้าเว็บจะเตือนก่อนปิด)

![หน้าเขียน MOM](docs/screenshot-write.png)
![หน้าตรวจ Export และร่างอีเมล](docs/screenshot-send.png)

## ความสามารถ

- **เขียน MOM:** ข้อมูลการประชุม, ผู้เข้าร่วม, วาระหรือหัวข้อพร้อมรายละเอียดและผลสรุป, งานที่ต้องดำเนินการ, หัวข้อข้อความอิสระ และช่องลงนาม
- **ปรับหัวข้อได้อิสระ:** เปลี่ยนชื่อ, เลื่อนลำดับ, ย่อ, นำออก (มีเลิกทำ), เพิ่มหัวข้อกำหนดเอง, ซ่อนหรือแสดงช่องข้อมูลการประชุม หัวข้อที่เว้นว่างจะไม่แสดงในเอกสาร
- **ปรับคำในหัวข้อวาระได้:** คำนำหน้าแต่ละข้อ (เช่น `วาระที่`, `หัวข้อที่`, `เรื่องที่` หรือเว้นว่าง) และชื่อช่องผลสรุป (เช่น `มติที่ประชุม`, `Next Step`)
- **ช่องลงนาม:** ตำแหน่ง ชื่อ วันที่ และลายเซ็น (วาด หรืออัปโหลดรูป) ช่องที่ไม่มีลายเซ็นหรือวันที่จะเป็นเส้นประให้เขียนด้วยปากกา
- **แม่แบบ 4 แบบ:** ประชุมทีมทั่วไป, หัวข้อ + Next Step, รายงานการประชุมแบบทางการ (5 ระเบียบวาระ), หน้าว่าง
- **Export PDF:** สร้างไฟล์ PDF ขนาด A4 รูปแบบเรียบในเบราว์เซอร์ (ไม่ต้องใช้เซิร์ฟเวอร์) พร้อมเลขหน้า
- **ร่างอีเมล:**
  - สร้างร่างใน Gmail ผ่าน Gmail API (scope `gmail.compose` ใช้สร้างฉบับร่างเท่านั้น ไม่ส่งเอง) แนบ PDF ได้
  - ดาวน์โหลดไฟล์ `.eml` ที่เปิดเป็นร่างใน Outlook / Thunderbird / Apple Mail แนบ PDF ได้
  - เปิดหน้าเขียนอีเมล Gmail หรือ Outlook บนเว็บ พร้อมผู้รับและหัวเรื่อง และคัดลอกเนื้อหาให้วาง

## Deploy

วางไฟล์ทั้งโฟลเดอร์บนเว็บเซิร์ฟเวอร์ static ใดก็ได้ (Nginx, Apache, IIS, S3, GitHub Pages, Netlify ฯลฯ) ไม่มีขั้นตอน build ไม่มีฐานข้อมูล

```
index.html            แอปทั้งหมด (HTML + CSS + JavaScript)
vendor/               html2pdf.js 0.14.0 (MIT) สำหรับ Export PDF
fonts/                ฟอนต์ Sarabun และ Anuphan (SIL OFL 1.1) ใช้แทน Google Fonts เมื่อออฟไลน์
docs/                 ภาพหน้าจอสำหรับ README
start.bat / start.ps1 เปิดใช้งานบน Windows แบบ local (PowerShell, พอร์ต 8765)
start.sh              เปิดใช้งานบน macOS / Linux แบบ local (python3)
```

### ค่าที่ต้องตั้งตอน deploy

แก้บล็อก `CONFIG` ด้านบนของ `<script>` ใน `index.html`:

```js
const CONFIG = {
  GMAIL_CLIENT_ID: '',   // Google OAuth Client ID สำหรับปุ่ม "สร้างร่างใน Gmail"
  SENDER_NAME: '',       // ชื่อลงท้ายเอกสารและอีเมล (ปล่อยว่าง = ใช้ชื่อผู้จดบันทึก)
  CLOSING: 'ขอแสดงความนับถือ',
  REPLY_NOTE: 'หากมีข้อแก้ไขหรือข้อเพิ่มเติม กรุณาแจ้งกลับภายใน 2 วันทำการ',
  PDF_GREETING: true     // ใส่คำขึ้นต้นและคำลงท้ายแบบจดหมายใน PDF
};
```

ผู้ใช้แก้ค่าเหล่านี้ได้ในหน้า "ตั้งค่า" แต่จะหายเมื่อปิดหน้า

### Gmail API (ไม่บังคับ)

1. [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → เปิดใช้ **Gmail API**
2. **OAuth consent screen** → User type **Internal**
3. **Credentials → OAuth client ID** → ประเภท **Web application**
4. **Authorized JavaScript origins** ใส่โดเมนที่ deploy เช่น `https://mom.tgfone.com` (และ `http://localhost:8765` ถ้าใช้ทดสอบในเครื่อง)
5. นำ Client ID ไปใส่ใน `CONFIG.GMAIL_CLIENT_ID`

ถ้าไม่ตั้งค่า ปุ่มอื่นทั้งหมดยังใช้งานได้ (Export PDF, .eml, เปิดหน้าเขียนอีเมลบนเว็บ)

### การเชื่อมต่อภายนอกที่หน้าเว็บเรียกใช้

| ปลายทาง | ใช้ทำอะไร | จำเป็นไหม |
|---|---|---|
| `fonts.googleapis.com`, `fonts.gstatic.com` | ฟอนต์ (มีสำรองใน `fonts/`) | ไม่ |
| `accounts.google.com/gsi/client` | เข้าสู่ระบบ Google เพื่อสร้างร่าง Gmail | เฉพาะเมื่อใช้ Gmail API |
| `gmail.googleapis.com` | สร้างฉบับร่าง | เฉพาะเมื่อใช้ Gmail API |

ถ้าใช้ Content Security Policy ให้อนุญาตโดเมนเหล่านี้ตามที่ใช้

## ใช้งานในเครื่อง

| ระบบ | วิธีเปิด |
|---|---|
| Windows | ดับเบิลคลิก `start.bat` |
| macOS / Linux | `sh start.sh` |

เปิด `index.html` ตรง ๆ ได้ แต่ Gmail API จะใช้ไม่ได้ และฟอนต์สำรองในโฟลเดอร์ `fonts/` จะไม่ทำงาน

## พัฒนาต่อ

แก้ `index.html` แล้วรีเฟรชเบราว์เซอร์ ส่วนสำคัญในสคริปต์:

- `TEMPLATES` คือแม่แบบตอนสร้าง MOM ใหม่
- `SEC` คือชนิดหัวข้อ (`attendees`, `agenda`, `actions`, `text`, `signoff`)
- `docHtml()` สร้างเอกสารสำหรับ PDF และการพิมพ์ (CSS อยู่ใต้ `.pdfdoc`)
- `emailHtml()` / `emailText()` สร้างเนื้อหาอีเมล
- `buildPdf()` สร้าง PDF ด้วย html2pdf.js โดยแทรก zero-width space ระหว่างคำไทย (`Intl.Segmenter`) เพื่อให้ตัดบรรทัดได้ถูกต้อง
- `buildMime()` ประกอบอีเมล MIME สำหรับ `.eml` และ Gmail API (แนบ PDF เป็น `multipart/mixed`)

## License ของส่วนประกอบภายนอก

- html2pdf.js: MIT (`vendor/html2pdf.LICENSE`, `vendor/html2pdf.bundle.min.js.LICENSE.txt`)
- Sarabun, Anuphan: SIL Open Font License 1.1 (`fonts/OFL-*.txt`)
