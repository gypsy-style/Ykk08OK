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

    /* 発送件数・個数。金額より目立たせない */
    .analytics_sub {
        margin-right: .5em;
        color: #888;
        font-size: .85em;
        font-weight: normal;
    }

    /* 直近注文のないサロンの4カラム。
       親の .record_block が 1040px 以上で flex になるため、幅を指定しないと
       内容幅に縮んで右側が余り、4カラムが均等に並ばない */
    .analytics_dormant {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        width: 100%;
    }

    .analytics_dormant_col {
        flex: 1 1 200px;
        min-width: 0;
    }

    @media only screen and (max-width: 678px) {
        .analytics_dormant {
            display: block;
        }

        .analytics_dormant_col:not(:last-child) {
            margin-bottom: 20px;
        }
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

    /* 基本の白背景だと白いカードに埋もれてボタンに見えないため敷き直す */
    .analytics_pnavi a {
        background-color: var(--color_pglay);
    }

    .analytics_pnavi a:hover {
        background-color: var(--color_lglay);
    }

    .analytics_more {
        margin-top: 15px;
        text-align: center;
    }
</style>
