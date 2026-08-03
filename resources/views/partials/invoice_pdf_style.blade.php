<style>
body {
  font-family: "Yu Gothic", "Hiragino Kaku Gothic ProN", sans-serif;
  background: #f5f5f5;
  padding: 20px;
  margin: 0;
}

.invoice {
  width: 100%;
  max-width: 760px;
  margin: auto;
  background: #fff;
  padding: 22px 42px 34px;
  color: #333;
  box-sizing: border-box;
  font-size: 13px;
  line-height: 1.7;
}

.invoice p {
  margin: 0;
}

.inv-title {
  text-align: center;
  margin: 0 0 34px;
}

.inv-title span {
  display: inline-block;
  font-size: 30px;
  font-weight: bold;
  letter-spacing: 10px;
  text-indent: 10px;
  padding-bottom: 10px;
  border-bottom: 3px solid #1b3a6b;
}

.inv-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 30px;
  margin-bottom: 26px;
}

.inv-to {
  font-size: 19px;
  font-weight: bold;
  padding-bottom: 6px;
  border-bottom: 1px solid #333;
  min-width: 240px;
}

.inv-meta {
  text-align: right;
  white-space: nowrap;
}

.inv-amount {
  display: flex;
  align-items: center;
  border: 1px solid #dbe3ed;
  border-radius: 8px;
  background: #f1f5fa;
  padding: 20px 28px;
  margin-bottom: 30px;
}

.inv-amount_main {
  flex: 1;
}

.inv-amount_label {
  font-size: 14px;
  font-weight: bold;
}

.inv-amount_value {
  font-size: 34px;
  font-weight: bold;
  letter-spacing: 1px;
  text-align: center;
}

.inv-amount_due {
  flex: 0 0 220px;
  text-align: center;
  border-left: 1px solid #cfd9e5;
  padding-left: 20px;
}

.inv-amount_due strong {
  display: block;
  font-size: 18px;
}

.inv-company {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  padding-bottom: 24px;
  border-bottom: 1px solid #ccc;
  margin-bottom: 24px;
}

.inv-company_name {
  font-size: 17px;
  font-weight: bold;
  margin-bottom: 6px !important;
}

.inv-company img {
  width: 110px;
  height: auto;
}

.invoice .inv-section {
  font-size: 15px;
  font-weight: bold;
  border-left: 4px solid #1b3a6b;
  padding-left: 9px;
  margin: 0 0 14px;
}

table {
  width: 100%;
  border-collapse: collapse;
}

.inv-items th,
.inv-items td {
  border: 1px solid #d5d5d5;
  padding: 11px 14px;
  text-align: center;
}

.inv-items th {
  background: #ececec;
  font-weight: bold;
}

.inv-items td:first-child,
.inv-items th:first-child {
  text-align: left;
}

.inv-summary {
  border: 1px solid #d5d5d5;
  margin-top: 14px;
}

.inv-summary td {
  padding: 7px 14px;
}

.inv-summary td:first-child {
  padding-left: 24px;
}

.inv-summary td:last-child {
  text-align: right;
}

.inv-summary tr.is-total td {
  background: #f1f5fa;
  color: #1b3a6b;
  font-size: 19px;
  font-weight: bold;
  padding: 10px 14px;
}

.inv-foot {
  display: flex;
  gap: 30px;
  margin-top: 28px;
}

.inv-foot_bank {
  flex: 1;
}

.inv-foot_note {
  flex: 2;
  border-left: 1px solid #ccc;
  padding-left: 30px;
}

.inv-foot p {
  font-size: 15px;
}

.inv-foot .inv-section {
  font-size: 19px;
}

.inv-thanks {
  text-align: center;
  margin-top: 30px !important;
  font-size: 12px;
}

.btn-area {
  text-align: center;
  margin-bottom: 20px;
}

button {
  padding: 12px 30px;
  background: #222;
  color: #fff;
  border: none;
  cursor: pointer;
  font-size: 16px;
  border-radius: 6px;
}

@page {
  size: A4;
  margin: 8mm;
}

@media print {
  body {
    background: #fff;
    padding: 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .btn-area {
    display: none;
  }

  .invoice {
    max-width: 100%;
    padding: 0;
  }

  .inv-title {
    margin-bottom: 24px;
  }

  .inv-amount {
    padding: 14px 24px;
    margin-bottom: 22px;
  }

  .inv-company {
    padding-bottom: 18px;
    margin-bottom: 18px;
  }

  .inv-items th,
  .inv-items td {
    padding: 8px 14px;
  }

  .inv-foot {
    margin-top: 20px;
  }

  .inv-thanks {
    margin-top: 22px !important;
  }

  .inv-items tr,
  .inv-summary tr {
    page-break-inside: avoid;
  }
}
</style>
