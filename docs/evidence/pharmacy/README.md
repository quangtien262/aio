# Pharmacy — evidence ngày 22/10/2026

Bằng chứng kiểm thử của working tree, chưa commit/deploy. [Bản tổng JSON](verification-2026-10-22.json) ghi nguồn và thứ tự từng lượt chạy. Các lượt có phần chồng lắp, **không cộng dồn** số tests/assertions.

| Lượt chạy | Kết quả |
|---|---|
| Full suite lịch sử | 1.252 tests / 34.190 assertions; 1.170 pass, 52 failures, 30 errors |
| Đối chứng HEAD `f4a9333b` | Tái hiện **80/82** case lỗi: 50 failures + 30 errors; không còn case chưa đối chiếu |
| Focused broad | PASS 215 tests / 5.830 assertions |
| Owned core, là subset | PASS 36 / 305: Transaction 17, Workspace 12, Workflow 4, Recall 3 |
| Release filter | PASS [75 / 568](release-final.junit.xml) |
| Administration sau sửa cuối | PASS [9 / 98](administration-final.junit.xml) |
| FE | PASS 34 JS tests, 6 synthetic browser tests, 2 route tests và production build |
| Browser qua PHP HTTP thật, chạy riêng | PASS 1 test, thời gian được báo 19,4 giây |
| MySQL native / shared Inventory | PASS 3 gates / 13 assertions và 5 gates / 8 assertions |
| Static checks | PASS Pint 68 files, lint 83 PHP files, diff check |

[Case-by-case baseline](baseline-comparison-f4a9333b.json) giữ nguyên lỗi lịch sử, đã chuẩn hóa đường dẫn. Hai delta mới được sửa riêng: expectation Inventory `0.4.0` → `0.5.0` trong Manufacturing fresh-install và inline API URL của Pharmacy. Cả hai pass trong release JUnit. Điều này không biến full snapshot cũ thành một lượt full-green.

Release filter 75/568 có Administration 8/92. Sau đó sửa timezone và alias GTIN chỉ zero-padding, rerun Administration **9/98**, thêm đúng một test. Không cộng nguyên 9 tests vào 75; các mốc trung gian 9/96 đã được thay bằng kết quả cuối 9/98.

## Local service profiles

Cùng máy Apple M1 Pro 10 cores / 32 GiB, PHP 8.5.5, MySQL 8.0.44; chạy tuần tự, **4 service streams**, 2 sales/điểm. Full OTC cycle gồm create → prepare → review → finalize → handover.

| Điểm | Sales | Assertions | p50 / p95 / max, ms | Active window, s | Sales/s active |
|---:|---:|---:|---|---:|---:|
| [10](pharmacy-load-10x2-w4.json) | 20 | 68 | 417,621 / 505,761 / 516,648 | 2,459298 | 8,132 |
| [50](pharmacy-load-50x2-w4.json) | 100 | 268 | 433,709 / 595,973 / 663,523 | 11,303219 | 8,847 |
| [100](pharmacy-load-100x2-w4.json) | 200 | 518 | 448,274 / 581,143 / 698,959 | 23,250496 | 8,602 |

Cả ba pass tiền/tồn/exact lot/handover/đối soát ca, không rò reservation; scratch đã xóa và xác minh. JSON giữ thêm throughput gồm worker boot/barrier, thời gian setup, cấu hình DB và giới hạn đo.

Đây không phải 100 streams đồng thời, HTTP/WAN, nhiều quầy tranh cùng kho hay chứng nhận capacity production. Dữ liệu chỉ synthetic OTC một dòng, mỗi điểm có actor/quầy/ca/kho riêng; không Rx/recall/cold chain. FE build ngắn có thể gây nhiễu; không giám sát độc lập background CPU/process. Production provider và các release gate cần nghiệm thu riêng.

## Ảnh giao diện

[Desktop](pharmacy-native-desktop.png) · [Mobile](pharmacy-native-mobile.png).

Không giữ `.env`, credentials, tokens, clinical data hoặc connection secrets. Hai JUnit đã chuẩn hóa đường dẫn workspace; benchmark chỉ chứa metadata máy/DB và dữ liệu tổng hợp.
