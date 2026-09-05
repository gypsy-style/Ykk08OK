<style>
    .analytics_tbl tbody tr:first-child {
        background-color: #dbe4f0;
        font-weight: bold;
    }

    .analytics_tbl tbody tr:nth-child(even) {
        background-color: #f2f5fa;
    }

    .analytics_tbl tbody tr:not(:last-child) {
        border-bottom: 1px solid #dfe5ee;
    }

    .analytics_tbl tbody tr:not(:first-child):hover {
        background-color: #e6edf8;
    }

    .analytics_tbl th a,
    .analytics_tbl td a {
        color: var(--color-text);
        text-decoration: underline;
    }

    .analytics_agency {
        margin-top: 5px;
        font-size: .9em;
    }

    /* 先月・次月を右端に横並びにする（float の左右振り分けを打ち消す） */
    .analytics_pnavi {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-bottom: 15px;
    }

    .analytics_pnavi li.prev,
    .analytics_pnavi li.next {
        float: none;
    }

    .analytics_more {
        margin-top: 15px;
        text-align: center;
    }
</style>
