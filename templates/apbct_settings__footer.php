<?php

use Cleantalk\ApbctWP\LinkConstructor;

add_action('admin_footer', 'apbct_settings__footer');

/**
 * Footer for settings page
 */
function apbct_settings__footer()
{
    $block1_links = [
        ['text' => 'CleanTalk Security for Websites', 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__spbct_link',
            'my',
            array(
                'cp_mode' => 'security',
            )
        )],
        ['text' => 'WordPress Malware Removal Service', 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__malware_removal_link',
            'wordpress-website-malware-removal',
            array(),
            'https://l.cleantalk.org'
        )],
        ['text' => 'Uptime Monitoring', 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__uptime_monitoring_link',
            'my',
            array(
                'cp_mode' => 'uptime_monitoring',
            )
        )],
        ['text' => 'SSL Certificates', 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__ssl_cert_link',
            'ssl-certificates/cheap-positivessl-certificate'
        )],
        ['text' => 'doBoard - online project management', 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__doboard_link',
            '',
            array(),
            'https://doboard.com'
        )],
    ];
    $block2_links = [
        ['text' => 'Top rated contact form by Everest', 'url' => 'https://wordpress.org/plugins/everest-forms/#description'],
    ];

    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const footerLinks = {
                block1: <?php echo json_encode($block1_links); ?>,
                block2: <?php echo json_encode($block2_links); ?>
            };

            const footer = document.createElement('div');
            footer.className = 'apbct_footer';

            function createFooterColumn(title, links) {
                const column = document.createElement('div');
                column.className = 'apbct_footer_column';

                const header = document.createElement('h3');
                header.className = 'apbct_footer_header';
                header.textContent = title;
                header.addEventListener('click', function () {
                    if (window.innerWidth <= 768) {
                        this.classList.toggle('active');
                        linksList.classList.toggle('active');
                    }
                });

                const linksList = document.createElement('ul');
                linksList.className = 'apbct_footer_links';
                links.forEach(link => {
                    const listItem = document.createElement('li');
                    const anchor = document.createElement('a');
                    anchor.href = link.url;
                    anchor.textContent = link.text;
                    anchor.target = '_blank';
                    listItem.appendChild(anchor);
                    linksList.appendChild(listItem);
                });

                column.appendChild(header);
                column.appendChild(linksList);
                return column;
            }

            footer.appendChild(createFooterColumn('More solutions for your site', footerLinks.block1));
            footer.appendChild(createFooterColumn('Recommended plugins', footerLinks.block2));

            const wpMainFooter = document.getElementById('wpfooter');
            const footerLeft = document.getElementById('footer-left');
            if (wpMainFooter && footerLeft) {
                wpMainFooter.insertBefore(footer, footerLeft);
            }
        });
    </script>
    <style>
        #wpfooter {
            display: block;
            background-color: #cccccc;
            bottom: auto;
        }
        .apbct_footer {
            display: flex;
            gap: 75px;
            margin-bottom: 20px;
        }
        .apbct_footer_column {
            margin: 0 0 20px 0px;
        }
        .apbct_footer_column h3 {
            cursor: pointer;
            margin-bottom: 10px;
            color: #3c434a;
        }
        .apbct_footer_links {
            list-style: none;
            padding: 0;
            margin: 0;
            column-count: 2;
            column-gap: 75px;
        }
        .apbct_footer_links li {
            margin-bottom: 5px;
        }
        .apbct_footer_links a {
            text-decoration: none;
            color: #3c434a;
        }
        .apbct_footer_links a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            #wpfooter {
                margin-left: 0 !important;
            }
            .apbct_footer {
                flex-direction: column;
                gap: 0px;
            }
            .apbct_footer_column {
                margin-bottom: 20px;
            }
            .apbct_footer_links {
                display: none;
                column-count: 1;
                column-gap: 0px;
            }
            .apbct_footer_links.active {
                display: block;
                column-count: 1;
            }
            .apbct_footer_header{
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .apbct_footer_header::after {
                background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='9' height='6' fill='none'%3E%3Cpath fill='%23000' d='M4.13 5.592a.5.5 0 0 0 .74 0l3.635-4.006a.5.5 0 0 0-.37-.836H.864a.5.5 0 0 0-.37.836z'/%3E%3C/svg%3E");
                background-size: 11px 7px;
                content: "";
                display: inline-flex;
                height: 7px;
                transition: transform .2s linear;
                width: 11px;
            }
            .apbct_footer_header.active::after {
                transform: rotate(-90deg);
            }
            .apbct_footer_column {
                justify-items: normal;
            }
        }
    </style>
    <?php
}