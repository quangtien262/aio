# Ghi chú nghiên cứu API hóa đơn điện tử (legacy, không phải contract runtime)

> Trạng thái: tài liệu nghiên cứu lịch sử, được giữ để truy vết quyết định và đối chiếu
> field của Minvoice/mSMI. Các đoạn nói về WHMCS hoặc “đã tạo” không mô tả source AIO
> hiện tại. Không dùng URL, header, payload mẫu hoặc quy tắc thuế trong file này để gọi
> production nếu chưa đối chiếu lại tài liệu nhà cung cấp, sandbox và quy định đang có hiệu lực.
>
> Contract runtime của AIO nằm trong module `accounting-tax` và `minvoice-connector`;
> kiến trúc/quy tắc vận hành có thẩm quyền nằm tại
> `docs/architecture/accounting-tax-module.md`. Mọi kết nối production phải vượt qua
> readiness gate, kill-switch và bước xác nhận chủ động theo từng pháp nhân.

THEO DÕI PHIÊN BẢN TÀI LIỆU 2.0
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/theo-doi-phien-ban-tai-lieu-20-M8if8n1API

1. DANH SÁCH CÁC HÀM API 2.0:
Đăng nhập lấy token
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/post-dang-nhap-lay-token-bRQyiOM2aM

🔵[GET] Lấy danh sách ký hiệu hóa đơn
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-lay-danh-sach-ky-hieu-hoa-don-tkBEdB5yVC

Thêm hóa đơn (overview):
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/them-hoa-don-json-mau-TCiTGzroaw

Hóa đơn GTGT thông thường, tem vé:
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/hoa-don-gtgt-thong-thuong-tem-ve-pbsCapRDar
Hóa đơn GTGT, tem vé máy tính tiền:
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/hoa-don-gtgt-tem-ve-may-tinh-tien-tsdWyG91Kr
Hóa đơn Bán hàng (Có giảm thuế theo NQ 204):
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/hoa-don-ban-hang-co-giam-thue-theo-nq-204-ehPJTvXfqz
Hoá đơn bán hàng máy tính tiền:
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/hoa-don-ban-hang-may-tinh-tien-QtS2MbnVXJ
Hóa đơn Bán hàng máy tính tiền (Có giảm thuế theo NQ 204)
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/hoa-don-ban-hang-may-tinh-tien-co-giam-thue-theo-nq-204-4WYCbyleIP
Phiếu xuất kho Kiêm vận chuyển nội bộ
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/phieu-xuat-kho-kiem-van-chuyen-noi-bo-7k98iXCaoh
Phiếu xuất kho hàng gửi bán đại lý
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/phieu-xuat-kho-hang-gui-ban-dai-ly-KsF6tuOseN
Sửa hoá đơn [JSON mẫu]
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/sua-hoa-don-json-mau-J8aVRu8uGg
Xóa hóa đơn [JSON mẫu]
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/xoa-hoa-don-json-mau-3xl1ImJoUV
[POST] Thêm mới, ký và gửi CQT ngay bằng FILE hoặc service:
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/post-them-moi-ky-va-gui-cqt-ngay-bang-file-hoac-service-eB0e1uzDp8
[POST] Ký hóa đơn và gửi CQT:
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/post-ky-hoa-don-va-gui-cqt-xctmPsmxpe
[POST] Thay thế hóa đơn
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/post-thay-the-hoa-don-DJWLWpFvfX


[POST] Điều chỉnh hóa đơn:
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/post-dieu-chinh-hoa-don-ILGFn6tKW1
⬆️
1. Điều chỉnh tăng đơn giá hàng hoá
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/1-dieu-chinh-tang-don-gia-hang-hoa-B1bn31OeUJ

2. Điều chỉnh tăng số lượng hàng hoá
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/2-dieu-chinh-tang-so-luong-hang-hoa-bzVk7bojqI
3. Điều chỉnh tăng thành tiền
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/3-dieu-chinh-tang-thanh-tien-aeKFMaFhtB
4. Điều chỉnh giảm đơn giá hàng hóa
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/4-dieu-chinh-giam-don-gia-hang-hoa-1ZJ3JLx9FX
5. Điều chỉnh giảm số lượng hàng hóa
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/5-dieu-chinh-giam-so-luong-hang-hoa-JXMPb2ZXZU
6. Điều chỉnh giảm thành tiền
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/6-dieu-chinh-giam-thanh-tien-nbeGkF9t9P
7. Điều chỉnh tên hàng hoá
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/7-dieu-chinh-ten-hang-hoa-qz6OdGpijh
8. Điều chỉnh đơn vị tính hàng hoá
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/8-dieu-chinh-don-vi-tinh-hang-hoa-RWndTcbQUi
9. Điều chỉnh mã số thuế người mua
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/9-dieu-chinh-ma-so-thue-nguoi-mua-Gv6xIgEDaa
10. Điều chỉnh hoá đơn về 0 (Tương đương huỷ hoá đơn)
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/10-dieu-chinh-hoa-don-ve-0-tuong-duong-huy-hoa-don-gZzfzWOLwO



🟢[POST] Thay thế hóa đơn trên hệ thống khác
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/post-thay-the-hoa-don-tren-he-thong-khac-bObLZdIZkH
[POST] Điều chỉnh hóa đơn trên hệ thống khác
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/post-dieu-chinh-hoa-don-tren-he-thong-khac-M7NcEfeQSg
🔵[GET] Tra cứu Mã số thuế và Căn cước công dân
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-tra-cuu-ma-so-thue-va-can-cuoc-cong-dan-x06xKkuUFW
[GET] Lấy thông tin hoá đơn thông qua Ký hiệu và Số hoá đơn
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-lay-thong-tin-hoa-don-thong-qua-ky-hieu-va-so-hoa-don-ur9mTdVmWf
[GET] Lấy thông tin hoá đơn thông qua id hóa đơn hoặc keyApi tích hợp từ đối tác
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-lay-thong-tin-hoa-don-thong-qua-id-hoa-don-hoac-keyapi-tich-hop-tu-doi-tac-mr8IxTfyR4
[GET] Lấy thông tin hóa đơn từ ngày - đến ngày và ký hiệu
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-lay-thong-tin-hoa-don-tu-ngay-den-ngay-va-ky-hieu-qrNtWhmBsk
[GET] Xem in hoá đơn thông qua id của hoá đơn và keyApi tích hợp từ đối tác
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-xem-in-hoa-don-thong-qua-id-cua-hoa-don-va-keyapi-tich-hop-tu-doi-tac-upw2iWS1li
[GET] Xem in hoá đơn chuyển đổi thông qua id của hoá đơn và keyApi tích hợp từ đối tác
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-xem-in-hoa-don-chuyen-doi-thong-qua-id-cua-hoa-don-va-keyapi-tich-hop-tu-doi-tac-Ko1ac0bGrR
[GET] Lấy file xml thông qua id của hoá đơn
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/get-lay-file-xml-thong-qua-id-cua-hoa-don-wsw9OnLyuS


ĐỊNH NGHĨA CÁC TRƯỜNG THÔNG TIN ĐẦU VÀO API
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/2-dinh-nghia-cac-truong-thong-tin-dau-vao-api-cAU1bxeBWI
Danh sách mã lỗi
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/danh-sach-ma-loi-tnu7EL3cIv
Danh sách trạng thái hóa đơn
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/danh-sach-trang-thai-hoa-don-pLOvOnFe23
Validate định dạng mã số thuế
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/validate-dinh-dang-ma-so-thue-ubHi5c0K4K
Validate định dạng căn cước công dân
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/validate-dinh-dang-can-cuoc-cong-dan-9rNYCs9280

Mô hình tích hợp hóa đơn điện tử khởi tạo từ Máy tính tiền
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/mo-hinh-tich-hop-hoa-don-dien-tu-khoi-tao-tu-may-tinh-tien-wLqsjRxwbU
Mã hóa Mã tra cứu
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/ma-hoa-ma-tra-cuu-N7WXw7ZAVV
Mã cơ quan thuế cấp
https://wiki.minvoice.com.vn/s/ebefcf08-59ff-49ce-806e-3ce03cda2d2b/doc/ma-co-quan-thue-cap-lDMhlKR05d




## Study notes: kha thi tich hop Minvoice vao WHMCS

Ngay study: 2026-06-12

### Ket luan nhanh

Co the tich hop API hoa don dien tu Minvoice vao module WHMCS hien tai voi muc kha thi cao. He thong WHMCS da co nen du lieu can thiet gom:

- `whmcs_invoices`: hoa don noi bo, trang thai thanh toan/van hanh.
- `whmcs_invoice_items`: dong hang hoa/dich vu, don gia, so luong, thanh tien, VAT theo dong.
- `whmcs_clients`: thong tin nguoi mua, email, dia chi, CCCD.
- `company_profiles`: thong tin don vi phat hanh, MST, dia chi, logo.
- `whmcs_tax_documents` va tax report: du lieu review ke toan/VAT.
- Attachment va activity log da co san de luu PDF/XML va audit.

Khong nen tron trang thai hoa don dien tu vao `whmcs_invoices.status`, vi `status` hien tai la trang thai thu tien/van hanh. Hoa don dien tu can mot lop trang thai phap ly rieng: da tao nhap tren Minvoice, cho ky, da ky, da gui CQT, thanh cong, loi, huy, dieu chinh, thay the.

### Nhom API Minvoice da doc va vai tro

- Dang nhap lay token:
  - `POST /api/Account/Login`
  - Input: `username`, `password`, `ma_dvcs`.
  - Output thanh cong: `code = "00"`, `ok = true`, `token`.
  - Dung cho service auth/cache token.

- Lay danh sach ky hieu hoa don:
  - `GET /api/Invoice68/GetTypeInvoiceSeries`
  - Header: `Authorization: Bear Token`.
  - Param `Type`: `1` GTGT, `2` ban hang, `5` tem ve, `6` phieu xuat kho, ...
  - Output co `khhdon`/`value`, `invoiceForm`, `invoiceYear`, `invoiceTypeName`.
  - Nen cache vao bang cau hinh ky hieu de admin chon khi phat hanh.

- Them hoa don JSON / hoa don GTGT thong thuong:
  - Payload chinh gom `editmode = 1`, `data[]`.
  - Cac field quan trong:
    - `inv_invoiceSeries`
    - `inv_invoiceIssuedDate`
    - `inv_currencyCode`
    - `inv_exchangeRate`
    - `inv_buyerDisplayName`
    - `inv_buyerLegalName`
    - `inv_buyerTaxCode`
    - `inv_buyerAddressLine`
    - `inv_buyerEmail`
    - `inv_paymentMethodName`
    - `inv_discountAmount`
    - `inv_TotalAmountWithoutVat`
    - `inv_vatAmount`
    - `inv_TotalAmount`
    - `key_api`
    - `details[].data[]`
  - Output tra ve `hoadon68_id`, `inv_invoiceAuth_id`, `inv_invoiceSeries`, `inv_invoiceNumber`, `trang_thai`, `is_tthdon`, `sobaomat`, `macqt`, ...

- Them moi, ky va gui CQT ngay:
  - `POST /api/InvoiceApi78/SaveSign`
  - Header: `Content-Type: application/json`, `Authorization: Bear Token`, `TaxCode: <MST>`.
  - Phu hop voi luong "phat hanh nhanh" khi invoice noi bo da du dieu kien.

- Ky hoa don va gui CQT:
  - `POST /api/InvoiceApi78/Sign`
  - Input: `hoadon68_id`.
  - Phu hop voi luong tao nhap truoc, ke toan review, sau do ky/gui.

- Sua/xoa hoa don cho ky:
  - Sua: `editmode = 2`, truyen `inv_invoiceAuth_id` hoac so hoa don.
  - Xoa: `editmode = 3`, truyen ky hieu + so hoa don, hoac `inv_invoiceAuth_Id` neu chua co so.
  - Chi nen cho thao tac khi Minvoice status con o trang thai nhap/cho ky theo rule nha cung cap.

- Tra cuu hoa don:
  - `GET /api/InvoiceApi78/GetInfoInvoice?id=...`
  - Hoac `GET /api/InvoiceApi78/GetInfoInvoice?keyApi=...`
  - Can cho sync trang thai va doi soat.

- Xem/in PDF:
  - `GET /api/InvoiceApi78/PrintInvoice?id=...`
  - Output thanh cong la byteArray PDF.
  - Nen tai ve luu attachment/path noi bo.

- Lay XML:
  - `GET /api/InvoiceApi78/ExportXml?id=...`
  - Output thanh cong co XML base64.
  - Nen decode va luu file XML theo invoice.

- Thay the hoa don:
  - `POST /api/InvoiceApi78/ThayThe`
  - Can `inv_originalId`, ngay/sovb/ghi_chu va payload hoa don thay the.
  - Nen lam phase sau, vi anh huong phap ly va audit.

- Dieu chinh hoa don:
  - `POST /api/InvoiceApi78/DieuChinh`
  - Can `inv_InvoiceAuth_id`, ngay hoa don dieu chinh va chi tiet dieu chinh.
  - Ho tro cac case tang/giam don gia, so luong, thanh tien, ten hang, don vi tinh, MST nguoi mua, dieu chinh ve 0.
  - Nen lam phase sau sau khi MVP phat hanh on dinh.

- Tra cuu MST/CCCD:
  - MST: `GET https://mst.minvoice.com.vn/api/System/SearchTaxCodeV2?tax={masothue}`
  - CCCD: `GET https://mst.minvoice.com.vn/api/System/SearchCMND?cid={cccd}`
  - Can gui IP server cho Minvoice whitelist truoc khi dung production.

### Mapping du lieu WHMCS sang Minvoice

Invoice header:

- `whmcs_invoices.ma_hoa_don` hoac `whmcs_invoices.id` -> `key_api`
- `whmcs_invoices.ma_hoa_don` -> `so_benh_an` hoac ma don hang tham chieu
- `ngay_phat_hanh` -> `inv_invoiceIssuedDate`
- `hinh_thuc_thanh_toan` -> `inv_paymentMethodName`
- `tong_phu` -> `inv_TotalAmountWithoutVat`
- `thue` -> `inv_vatAmount`
- `tong_tien` -> `inv_TotalAmount`
- `giam_gia` -> `inv_discountAmount`
- Mac dinh `inv_currencyCode = "VND"`, `inv_exchangeRate = 1`

Buyer:

- `whmcs_clients.ho_ten` -> `inv_buyerDisplayName`
- `whmcs_clients.company_name` -> `inv_buyerLegalName`
- Can bo sung field MST khach hang, vi hien `whmcs_clients` chua co `tax_code`/`ma_so_thue` rieng cho WHMCS -> `inv_buyerTaxCode`
- `whmcs_clients.dia_chi` -> `inv_buyerAddressLine`
- `whmcs_clients.email` -> `inv_buyerEmail`
- `whmcs_clients.cccd_so` -> `cccdan` hoac `buyerIdentityCard` tuy loai hoa don

Seller/company:

- `company_profiles.tax_code` -> header `TaxCode`
- `company_profiles.company_name/address/email/hotline` dung cho preview noi bo va doi chieu.

Invoice lines:

- `whmcs_invoice_items.mo_ta` -> `inv_itemName`
- `product_id` hoac generated code -> `inv_itemCode`
- Mac dinh `inv_unitCode = "Lan"` hoac cau hinh theo loai dich vu.
- `so_luong` -> `inv_quantity`
- `don_gia` -> `inv_unitPrice`
- `giam_gia` -> `inv_discountAmount`
- `thanh_tien` -> `inv_TotalAmountWithoutVat`
- `vat_amount` -> `inv_vatAmount`
- `thanh_tien + vat_amount` -> `inv_TotalAmount`
- `vat_rate` -> `ma_thue`

Can xac nhan voi Minvoice mapping `ma_thue`:

- Tai lieu dinh nghia co `10`, `5`, `0`, `-1`, `-2`.
- JSON mau co dung `8`.
- WHMCS domain co the dung `8`, nen can confirm Minvoice chap nhan `8` chinh thuc.
- Web AIO la san pham phan mem do cong ty tu phat trien va **KCT toan bo dong**:
  - khong split thanh Platform + Hosting khi tao hoa don dien tu
  - server/hosting kem theo chi la chi phi van hanh noi bo, khong phai dong chiu VAT dau ra
  - gui mot dong Web AIO duy nhat voi `ma_thue = -1`, `inv_vatAmount = 0`, `inv_TotalAmount = inv_TotalAmountWithoutVat`
  - owner logic: `app/Services/Whmcs/WhmcsMinvoiceService.php`

### Bang du lieu nen them

`whmcs_einvoice_configs`

- `provider` (`minvoice`)
- `company_profile_id`
- `base_url`
- `tax_code`
- `username`
- `password_encrypted`
- `ma_dvcs`
- `default_invoice_type`
- `default_invoice_series`
- `signing_mode` (`draft_then_sign`, `save_sign`)
- `environment` (`test`, `production`)
- `is_active`
- `metadata`

`whmcs_einvoice_series`

- `config_id`
- `provider_series_id`
- `series`
- `invoice_form`
- `invoice_year`
- `invoice_type_name`
- `raw_payload`
- `synced_at`

`whmcs_einvoice_documents`

- `invoice_id`
- `provider`
- `config_id`
- `key_api`
- `provider_invoice_id` (`hoadon68_id`)
- `invoice_auth_id`
- `invoice_series`
- `invoice_number`
- `issue_date`
- `legal_status` noi bo (`draft`, `waiting_sign`, `signed`, `sent`, `accepted`, `error`, `cancelled`, `adjusted`, `replaced`)
- `provider_trang_thai`
- `provider_is_tthdon`
- `tax_authority_code` (`macqt`)
- `lookup_code` (`sobaomat`)
- `pdf_path`
- `xml_path`
- `last_error_code`
- `last_error_message`
- `request_payload_snapshot`
- `response_payload_snapshot`
- `synced_at`
- `issued_by_admin_id`
- timestamps

`whmcs_einvoice_logs`

- `einvoice_document_id`
- `invoice_id`
- `action` (`login`, `sync_series`, `create`, `sign`, `save_sign`, `sync`, `download_pdf`, `download_xml`, `delete_draft`, `replace`, `adjust`)
- `status` (`success`, `failed`)
- `http_status`
- `provider_code`
- `message`
- `request_payload`
- `response_payload`
- `admin_user_id`
- timestamps

### Luong MVP de trien khai

1. Cau hinh Minvoice trong admin WHMCS:
   - Nhap base URL, username, password, `ma_dvcs`, MST, mode test/live.
   - Nut `Kiem tra ket noi` goi login va hien ket qua.

2. Dong bo ky hieu hoa don:
   - Goi `GetTypeInvoiceSeries`.
   - Luu series vao bang cache.
   - Cho chon series mac dinh theo company profile/MST.

3. Bo sung thong tin xuat hoa don cho client:
   - Them MST khach hang.
   - Them ten phap ly va dia chi xuat hoa don neu can tach khoi `company_name`/`dia_chi`.
   - Co nut tra cuu MST/CCCD neu da whitelist IP.

4. Preview payload Minvoice tu invoice WHMCS:
   - Tao endpoint noi bo build payload, chua gui Minvoice.
   - Hien cho ke toan soat: buyer, series, line items, VAT, tong tien.

5. Tao hoa don nhap tren Minvoice:
   - Goi API them hoa don voi `editmode = 1`.
   - Luu `hoadon68_id`, `invoice_auth_id`, `trang_thai`, raw response.

6. Ky va gui CQT:
   - Neu dung luong tach rieng: goi `/InvoiceApi78/Sign`.
   - Neu dung luong nhanh: goi `/InvoiceApi78/SaveSign`.
   - Sau khi ky/gui, sync lai thong tin hoa don.

7. Tai PDF/XML:
   - Goi `PrintInvoice` va `ExportXml`.
   - Luu file vao disk public/private theo convention attachment hien co.
   - Hien nut tai file trong drawer hoa don.

8. Sync trang thai:
   - Goi `GetInfoInvoice` theo `id` hoac `keyApi`.
   - Map:
     - `trang_thai = 0`: Cho duyet
     - `1`: Cho ky
     - `2`: Da ky
     - `3`: Da gui
     - `4`: Thanh cong
     - `5`: Co loi
     - `6`: Dang ky
   - `is_tthdon`: `0` goc, `1` huy, `2` dieu chinh, `3` thay the, `5` bi thay the, `6` bi dieu chinh.

### UI de xuat

Trong drawer chi tiet hoa don WHMCS them section/tab `Hoa don dien tu`:

- Trang thai Minvoice.
- Ky hieu/so hoa don.
- Ma CQT.
- Ma tra cuu.
- Ngay phat hanh.
- Nut:
  - Preview payload
  - Tao hoa don dien tu
  - Ky va gui CQT
  - Dong bo trang thai
  - Tai PDF
  - Tai XML
  - Xem log
- Chi hien thao tac sua/xoa nhap neu hoa don Minvoice con o trang thai cho ky/chua gui.
- Khi da gui/thanh cong, neu can sua thi chuyen sang luong dieu chinh/thay the, khong sua truc tiep invoice phap ly.

### Rui ro va viec can confirm

- Header `Authorization`: tai lieu ghi `Bear Token`, co noi ghi `Bear Token;VP`. Can confirm format chuan khi goi production.
- Chu ky so: API ky/gui phu thuoc FILE p12 hoac service EASY/ICA/INTRUST. Can biet khach dang dung mode nao.
- `ma_thue = 8`: JSON mau co dung, nhung bang dinh nghia chi liet ke `10`, `5`, `0`, `-1`, `-2`. Can confirm voi Minvoice de tranh loi VAT 8%.
- Web AIO phai di theo `ma_thue = -1` (KCT), khong dung `0` va khong tao dong Hosting VAT 10%.
- Ngay hoa don co rule nghiem: loi `294`, `295`, `296` lien quan ngay hoa don khong hop le/khong tuan tu. Can khoa UI hoac canh bao neu phat hanh lui ngay.
- API MST/CCCD can whitelist IP production.
- Sau khi hoa don da gui CQT thanh cong, thay doi invoice noi bo co the lam lech chung tu phap ly. Can lock hoac hien warning khi invoice co `einvoice_document` da accepted/sent.
- `key_api` phai unique; nen dung `whmcs-invoice-{id}` hoac `ma_hoa_don` co prefix moi truong de tranh trung giua test/prod.

### Phase de trien khai

Phase 1 - Nen tang:

- Config Minvoice.
- Login/cache token.
- Sync series.
- Them MST/thong tin xuat hoa don cho client.
- Preview payload.

Implementation note 2026-06-12:

- Da tao module rieng trong WHMCS dashboard: route FE `whmcs/einvoices`, API prefix `whmcs/einvoices`.
- Da them cac bang:
  - `whmcs_einvoice_configs`
  - `whmcs_einvoice_series`
  - `whmcs_einvoice_documents`
  - `whmcs_einvoice_logs`
- Mat khau Minvoice luu trong DB bang Laravel `Crypt`, khong hardcode vao source.
- Da co API backend:
  - Luu cau hinh Minvoice.
  - Test login.
  - Sync ky hieu hoa don.
  - Preview payload Minvoice tu invoice WHMCS.
  - Sync thong tin hoa don Minvoice ve bang noi bo theo `key_api` hoac `hoadon68_id`.
- Chua tu dong test login bang credential duoc cung cap vi chua co base URL tenant Minvoice chinh xac; khong nen doan domain de tranh gui mat khau sang sai dich.

Phase 2 - Phat hanh co ban:

- Tao hoa don GTGT thong thuong tu WHMCS invoice.
- Ky/gui CQT.
- Sync status.
- Tai PDF/XML.
- Hien trong invoice drawer.

Phase 3 - Ke toan/audit:

- Gan e-invoice vao tax document/report.
- Export danh sach doi soat.
- Log request/response co masked credentials.
- Canh bao invoice noi bo thay doi sau khi da phat hanh.

Phase 4 - Nghiep vu nang cao:

- Sua/xoa hoa don cho ky.
- Thay the hoa don.
- Dieu chinh hoa don.
- Huy/dieu chinh ve 0.
- Tra cuu MST/CCCD truc tiep tren client drawer.

## Study notes 2026-06-18: dong bo hoa don dien tu dau vao tu thue qua Minvoice mSMI

### Nguon tai lieu da doc

- Workspace mSMI: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20`
- Theo doi phien ban tai lieu: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/theo-doi-phien-ban-tai-lieu-msmi-leAznQYaoQ`
- Khai bao token tich hop: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/cach-khai-bao-token-tich-hop-SWKIXescif`
- Lay danh sach hoa don: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/lay-danh-sach-hoa-don-VviFuLJCbO`
- Dinh nghia field tra ve: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/dinh-nghia-cac-truong-du-lieu-tra-ve-jwmDIjrd3B`
- Tai XML: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/tai-file-xml-hoa-don-HdSKLwHxjM`
- Tai mau in PDF/html: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/tai-mau-in-pdfhtml-hoa-don-NH64b4Kyul`
- Kiem tra hoa don tu XML: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/lay-thong-tin-kiem-tra-hoa-don-du-lieu-xml-IjOfFAgGPH`
- Kiem tra trang thai MST: `https://wiki.minvoice.com.vn/s/46d290ac-c258-40ac-afb3-35bba03a5c20/doc/kiem-tra-trang-thai-ma-so-thue-QqZfKZCdNU`

### Ban chat API mSMI

mSMI la nhom API doc du lieu hoa don da dong bo voi CQT, khac voi module Minvoice xuat hoa don dau ra dang dung trong WHMCS.

- Dieu kien: doanh nghiep/MST can co tai khoan mSMI va da ket noi voi CQT.
- Moi request dung static `apiToken` trong header, khong phai luong login lay bearer token nhu API xuat hoa don dau ra.
- Production domain tai lieu neu la `qlhd.minvoice.com.vn`, test domain la `test-qlhd.minvoice.com.vn`.
- Mot don vi co the tao nhieu token, token co mo ta va ngay het han; neu de trong ngay het han thi token dung vo thoi han.
- Scope API co the lay hoa don dau vao va dau ra, nhung nghiep vu hien tai chi can `invoiceType=INPUT_ELECTRONIC_INVOICE`.

### API lay danh sach hoa don

Endpoint GET, co 2 duong dan tuong duong:

- `https://test-qlhd.minvoice.com.vn/erp/qlhd-api/invoices`
- `https://test-qlhd.minvoice.com.vn/api/qlhd-api/invoices`

Header:

- `apiToken: <token khai bao tren mSMI>`

Params quan trong:

- `page`: trang can lay. Tai lieu noi khong duoc lon hon `totalPage`.
- `size`: bat buoc, so ban ghi/trang, toi da 200.
- `invoiceType`: bat buoc.
  - `INPUT_ELECTRONIC_INVOICE`: hoa don dau vao.
  - `OUTPUT_ELECTRONIC_INVOICE`: hoa don dau ra.
- `invoiceReleaseDateFrom`: tu ngay, format `dd/MM/yyyy`.
- `invoiceReleaseDateTo`: den ngay, format `dd/MM/yyyy`.
- `generalNotation`: ky hieu hoa don.
- `generalInvoiceNo`: so hoa don.
- `sellerTaxNo`, `sellerName`: loc ben ban.
- `buyerTaxNo`, `buyerName`: loc ben mua.

Response thanh cong gom:

- `status`
- `message`
- `totalInvoice`
- `totalPage`
- `numberOfInvoicePerPage`
- `currentPage`
- `numberOfInvoiceInCurrentPage`
- `listInvoice`

Response loi vi thieu param co the chi tra:

- `message`

### Field hoa don can quan tam

Field dinh danh/phap ly:

- `_id`: ID MongoDB cua hoa don tren mSMI, dung lai de tai XML/PDF-html va kiem tra warning. Day la khoa sync quan trong nhat.
- `id`: ID hoa don luu tren CQT.
- `type`: `purchase` la mua vao, `sold` la ban ra.
- `nbmst`: MST nguoi ban.
- `nbten`: ten nguoi ban.
- `nbdchi`: dia chi nguoi ban.
- `nmmst`: MST nguoi mua.
- `nmten`: ten don vi mua hang.
- `khmshdon`: ky hieu mau so hoa don.
- `khhdon`: ky hieu hoa don.
- `shdon`: so hoa don.
- `mhdon`: ma hoa don.
- `tdlap`: thoi diem lap hoa don.
- `ncma`: ngay CQT cap ma.
- `ntnhan`: ngay CQT tiep nhan ban ghi.
- `last_upDated_Date`: ngay update cuoi cua ban ghi.

Field tien/thue:

- `dvtte`: don vi tien te.
- `tgia`: ty gia.
- `tgtcthue`: tong tien truoc thue.
- `tgtthue`: tong tien thue.
- `tgtttbso`: tong tien thanh toan bang so.
- `tgtttbchu`: tong tien thanh toan bang chu.
- `ttcktmai`: tong chiet khau thuong mai.
- `tgtkcthue`: tong tien khong chiu thue.
- `tgtphi`: tong tien phi.
- `tgtkhac`: tong tien khac.
- `thttltsuat`: tong hop thanh toan theo thue suat, gom `tsuat`, `thtien`, `tthue`, `gttsuat`.

Field trang thai/canh bao:

- `tthai`: tinh chat/trang thai hoa don.
  - `1`: hoa don goc.
  - `2`: thay the.
  - `3`: dieu chinh.
  - `4`: bi thay the.
  - `5`: bi dieu chinh.
  - `6`: huy.
- `ttxly`: trang thai xu ly voi CQT.
  - `0`: Tong cuc thue da nhan.
  - `2`: CQT tu choi theo tung lan phat sinh.
  - `3`: hoa don du dieu kien cap ma.
  - `4`: hoa don khong du dieu kien cap ma.
  - `5`: da cap ma hoa don.
  - `6`: Tong cuc thue da nhan khong ma.
  - `8`: Tong cuc thue da nhan hoa don co ma tu may tinh tien.
- `bhphap`: co phai hoa don bat hop phap hay khong.
  - `0`: hop phap.
  - `1`: bat hop phap.
- `bhpldo`: ly do bat hop phap.
- `isHDTrung`: co phai hoa don trung.
- `hdTrung`: thong tin hoa don trung.

Field line hang hoa/dich vu trong `hdhhdvu`:

- `idhdon`: id dau phieu hoa don.
- `id`: ID chi tiet hoa don.
- `stt`: so thu tu.
- `ten`: ten hang hoa/dich vu.
- `dvtinh`: don vi tinh.
- `sluong`: so luong.
- `dgia`: don gia.
- `thtien`: thanh tien truoc VAT.
- `tsuat`: thue suat VAT.
- `stckhau`: tien chiet khau.
- `tlckhau`: ty le chiet khau.
- `tchat`: tinh chat hang hoa/dich vu.

### API tai file va kiem tra

Tai XML:

- GET `/erp/qlhd-api/invoices/${_id}/download/invoice.xml`
- GET `/api/qlhd-api/invoices/${_id}/download/invoice.xml`
- Header `apiToken`.
- `_id` la ID MongoDB tra ve tu API danh sach.

Tai mau in PDF/html:

- GET `/erp/qlhd-api/invoices/${_id}/download/invoice.html`
- GET `/api/qlhd-api/invoices/${_id}/download/invoice.html`
- Header `apiToken`.
- Output la HTML hoa don, co the luu thanh file html hoac render/in lai noi bo.

Kiem tra warning/validity tu XML:

- GET `/erp/qlhd-api/invoices/${_id}/warning`
- GET `/api/qlhd-api/invoices/${_id}/warning`
- Header `apiToken`.
- Output co:
  - `status`: trang thai hien thi moi nhat, co the bi thay doi neu user an canh bao.
  - `origin_status`: trang thai goc chua bi an canh bao.
  - `xmlnven`: tinh toan ven XML, `0` la con nguyen ven, `1` la da bi sua sau khi ky.
  - `Date`: ngay kiem tra.
  - `has_xml`: hoa don co XML hay khong.
  - Cac field validate khac lien quan ngay ky, chu ky so, ngay cap ma, nguoi ban da dang ky HĐDT, hoa don da cap ma CQT.

Kiem tra trang thai MST:

- GET `https://mst.minvoice.com.vn/api/System/SearchTaxCodeV2?tax={MST}`
- Output co `ten_cty`, `masothue_id`, `ten_tthai`.
- Trang thai co the gap:
  - NNT da duoc cap MST.
  - NNT ngung hoat dong va da hoan thanh thu tuc cham dut hieu luc MST.
  - NNT ngung hoat dong nhung chua hoan thanh thu tuc cham dut hieu luc MST.
  - NNT dang hoat dong.

### Danh gia kha thi ap dung vao WHMCS

Kha thi cao, nhung khong nen tron truc tiep vao bang `whmcs_supplier_bills` ngay tu dau.

Ly do:

- `whmcs_supplier_bills` hien la chung tu dau vao noi bo/supplier bill, co workflow draft/confirmed/cancelled, co line nghiep vu domain/service expense, va duoc invoice item snapshot su dung lam dau vao ke toan.
- Hoa don tu CQT/mSMI la nguon du lieu phap ly ben ngoai, co raw payload, XML, warning, trang thai CQT, va co the co nhieu loai hoa don khong tuong ung ngay voi registrar/domain/service expense.
- Neu sync thang vao supplier bill thi moi lan sync lai co nguy co ghi de chung tu noi bo, lam lech lich su accounting review.

Nen thiet ke nhu module hoa don dien tu dau ra:

- Co module rieng `Hoa don dien tu dau vao`.
- Co bang mirror 1-1 voi hoa don dau vao tren mSMI/CQT.
- Co cot `supplier_bill_id` de map voi chung tu dau vao noi bo sau khi user review.
- Khi sync khong bat buoc phai map voi supplier bill.
- Sau khi sync, ke toan co the map hoac tao supplier bill noi bo tu hoa don dau vao da chon.
- Neu tao supplier bill tu hoa don dau vao, can luu snapshot/source link de audit nguon goc.

### DB de xuat

`whmcs_input_einvoice_configs`

- `id`
- `provider`: mac dinh `minvoice_msmi`.
- `name`
- `base_url`: vi du `https://test-qlhd.minvoice.com.vn` hoac `https://qlhd.minvoice.com.vn`.
- `tax_code`: MST don vi can dong bo.
- `api_token_encrypted`: token mSMI luu bang Laravel `Crypt`.
- `environment`: `test`/`production`.
- `is_active`
- `last_sync_at`
- `metadata`
- timestamps

Co the dung lai `whmcs_einvoice_configs` neu muon gom Minvoice config, nhung nen tach config dau vao vi auth khac hoan toan: static `apiToken` vs login/bearer token.

`whmcs_input_einvoice_documents`

- `id`
- `config_id`
- `supplier_bill_id` nullable, FK `whmcs_supplier_bills.id`.
- `provider`: `minvoice_msmi`.
- `invoice_type`: `INPUT_ELECTRONIC_INVOICE`.
- `provider_mongo_id`: map `_id`, unique theo config.
- `provider_tax_id`: map `id` CQT.
- `provider_type`: map `type`, ky vong `purchase`.
- `seller_tax_code`: `nbmst`.
- `seller_name`: `nbten`.
- `seller_address`: `nbdchi`.
- `buyer_tax_code`: `nmmst`.
- `buyer_name`: `nmten`.
- `template_code`: `khmshdon`.
- `invoice_series`: `khhdon`.
- `invoice_number`: `shdon`.
- `invoice_code`: `mhdon`.
- `invoice_name`: `thdon`.
- `invoice_kind_name`: `tlhdon`.
- `currency`: `dvtte`.
- `exchange_rate`: `tgia`.
- `issued_at`: `tdlap`.
- `tax_authority_code_issued_at`: `ncma`.
- `tax_authority_received_at`: `ntnhan`.
- `provider_updated_at`: `last_upDated_Date`.
- `subtotal_ex_vat`: `tgtcthue`.
- `vat_amount`: `tgtthue`.
- `total_amount`: `tgtttbso`.
- `non_taxable_amount`: `tgtkcthue`.
- `discount_amount`: `ttcktmai`.
- `fee_amount`: `tgtphi`.
- `other_amount`: `tgtkhac`.
- `invoice_status_code`: `tthai`.
- `processing_status_code`: `ttxly`.
- `illegal_status`: `bhphap`.
- `illegal_reason`: `bhpldo`.
- `duplicate_status`: `isHDTrung`.
- `warning_status`
- `warning_origin_status`
- `xml_integrity_status`: `xmlnven`.
- `has_xml`
- `xml_path`
- `html_path`
- `raw_payload`
- `warning_payload`
- `sync_status`: `synced`/`failed`/`stale`.
- `last_synced_at`
- timestamps

Unique/index de xuat:

- Unique `(config_id, provider_mongo_id)`.
- Index `(seller_tax_code, invoice_series, invoice_number)`.
- Index `(issued_at, seller_tax_code)`.
- Index `(supplier_bill_id)`.

`whmcs_input_einvoice_lines`

- `id`
- `input_einvoice_document_id`
- `provider_line_id`: line `id`.
- `provider_header_id`: `idhdon`.
- `line_no`: `stt`.
- `item_name`: `ten`.
- `unit`: `dvtinh`.
- `quantity`: `sluong`.
- `unit_price`: `dgia`.
- `subtotal_ex_vat`: `thtien`.
- `vat_rate`: `tsuat`.
- `discount_amount`: `stckhau`.
- `discount_rate`: `tlckhau`.
- `line_type`: `tchat`.
- `raw_payload`
- timestamps

### Sync strategy de xuat

1. Config module:
   - Base URL mSMI.
   - MST doanh nghiep.
   - Static API token.
   - Test connection bang API danh sach voi date range nho hoac API MST.

2. Sync theo khoang ngay:
   - Form chi can `tu ngay`, `den ngay`, optional filter seller MST/series/so hoa don.
   - Goi GET `qlhd-api/invoices` voi:
     - `invoiceType=INPUT_ELECTRONIC_INVOICE`
     - `page=1..totalPage`
     - `size=100` hoac `200`
     - date format `dd/MM/yyyy`
   - Upsert theo `(config_id, _id)`.
   - Neu record da ton tai, chi update khi `last_upDated_Date` moi hon hoac user tick `sync_lai`.
   - Luu raw payload day du de audit.

3. Tai file:
   - Nut tai XML/HTML trong drawer chi tiet hoa don dau vao.
   - Khi sync co the chua tai file de nhe he thong.
   - Tai lazy khi user bam, hoac batch tai theo lua chon.
   - File luu vao disk theo convention attachment hien co, vi day la chung tu ke toan can audit.

4. Kiem tra warning:
   - Nut `Kiem tra tinh hop le`.
   - Goi `/warning` theo `_id`.
   - Luu warning payload, hien tag `Hop le`/`Canh bao`, canh bao XML khong toan ven, hoa don bat hop phap, hoa don trung.

5. Mapping voi noi bo:
   - Drawer chi tiet document co section `Lien ket chung tu dau vao noi bo`.
   - User co the:
     - Lien ket voi `whmcs_supplier_bills` da co.
     - Tao supplier bill tu hoa don dau vao.
     - Bo lien ket.
   - Khi tao supplier bill tu document:
     - Header lay seller, so hoa don, ngay hoa don, tong tien/thue.
     - Line lay tu `whmcs_input_einvoice_lines`.
     - `metadata.source = input_einvoice_document`.
     - Attach/link XML/HTML neu da tai.
   - Khong nen tu dong confirm supplier bill. Nen de draft de ke toan review phan loai expense/domain/service period.

6. Bao cao thue:
   - Tax report dau vao nen uu tien supplier bill noi bo da confirmed.
   - Hoa don dau vao mSMI chua map la danh sach doi soat/chua hach toan, khong tu dong dua vao VAT dau vao cho den khi ke toan map/confirm.
   - Co man hinh doi soat:
     - Hoa don tu thue chua co supplier bill.
     - Supplier bill co VAT nhung chua map hoa don tu thue.
     - Lech tong truoc VAT/VAT/tong tien.

### UI de xuat

Route rieng trong WHMCS:

- FE: `whmcs/input-einvoices`
- API prefix: `whmcs/input-einvoices`
- Menu group: `Tai chinh`, label `Hoa don dau vao tu thue` hoac `Hoa don dien tu dau vao`.

Man hinh chinh:

- Toolbar:
  - `Cau hinh mSMI`
  - `Sync tu thue`
  - `Tai XML/HTML da chon`
  - `Kiem tra hop le da chon`
- Bang:
  - Lien ket noi bo.
  - Ky hieu/so hoa don.
  - Ngay lap.
  - Nguoi ban/MST.
  - Tong truoc VAT.
  - VAT.
  - Tong tien.
  - Trang thai CQT.
  - Hop le/canh bao.
  - Sync luc.
- Drawer:
  - Tong quan phap ly.
  - Nguoi ban/nguoi mua.
  - Dong hang hoa/dich vu.
  - Tong hop VAT.
  - File XML/HTML.
  - Warning/validity.
  - Lien ket supplier bill noi bo.
  - Raw JSON tab cho debug.

### Cach lam hop ly nhat cho he thong hien tai

Lam theo 4 phase:

Phase 1 - Read-only mirror:

- Them config mSMI token.
- Tao bang input e-invoice document/lines/logs.
- Sync theo date range, upsert 1-1 theo `_id`.
- UI list/drawer/doc detail.
- Tai XML/HTML va check warning.

Phase 2 - Mapping voi supplier bill:

- Them `supplier_bill_id` vao document.
- Modal `Lien ket chung tu dau vao noi bo`.
- Tao supplier bill draft tu input e-invoice.
- Doi soat tong tien/VAT.

Phase 3 - Accounting workflow:

- Man hinh review `Da map`/`Chua map`/`Lech so lieu`.
- Tax report chi lay VAT dau vao tu supplier bill confirmed, nhung hien badge/link nguon thue neu co.
- Canh bao khi supplier bill confirmed nhung document mSMI bi bat hop phap/trung/XML warning.

Phase 4 - Automation nang cao:

- Scheduled sync hang ngay.
- Sync incremental theo `last_upDated_Date`.
- Auto-suggest supplier bill map dua tren seller MST + invoice series + invoice number + total.
- Auto-create draft supplier bill cho nha cung cap da tin cay, nhung van can ke toan confirm.

### Rui ro/can xac nhan truoc khi code production

- Token mSMI la token tinh, can chinh sach luu ma hoa, rotate va log masked.
- Can xac nhan base URL production va MST nao da ket noi CQT.
- Tai lieu noi `size` khong duoc lon hon 200; UI/backend phai enforce.
- Field ngay tra ve dang String, can parse linh hoat timezone/date format.
- `_id` la khoa tai XML/HTML; khong duoc chi dung series+number vi co the trung khi thay the/dieu chinh/duplicate.
- Hoa don co `has_xml=false` co the la bang tong hop, khong nen xem la loi sync.
- `status` warning co the bi user an canh bao tren mSMI, nen luu ca `origin_status`.
- Khong tu dong ghi de supplier bill da confirmed khi sync lai hoa don tu thue.






