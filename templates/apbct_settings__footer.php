<?php

use Cleantalk\ApbctWP\LinkConstructor;

add_action('admin_footer', 'apbct_settings__footer');

/**
 * Footer for settings page
 */
function apbct_settings__footer()
{
    global $apbct;

    $block1_links = [
        ['text' => __('CleanTalk Security for Websites', 'cleantalk-spam-protect'), 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__spbct_link',
            'my',
            array(
                'cp_mode' => 'security',
                'user_token' => $apbct->user_token
            )
        )],
        ['text' => __('Uptime Monitoring', 'cleantalk-spam-protect'), 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__uptime_monitoring_link',
            'my',
            array(
                'cp_mode' => 'uptime_monitoring',
                'user_token' => $apbct->user_token
            )
        )],
        ['text' => __('doBoard - online project management', 'cleantalk-spam-protect'), 'url' => LinkConstructor::buildCleanTalkLink(
            'settings_footer__doboard_link',
            '',
            array(),
            'https://doboard.com'
        )],
    ];
    $block2_links = [
        ['text' => __('Everest Forms', 'cleantalk-spam-protect'), 'url' => 'https://tinyurl.com/apbct-everest-forms'],
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
                    const linkExternalIco = document.createElement('i')
                    linkExternalIco.className = 'apbct-icon-link-ext';
                    linkExternalIco.html = '&nbsp;';
                    anchor.href = link.url;
                    anchor.textContent = link.text;
                    anchor.target = '_blank';
                    anchor.append(linkExternalIco);
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
            background-color: #e6e5e5;
        }
        #wpcontent {
            padding-bottom: 250px;
        }
        .apbct_footer {
            display: flex;
            justify-content: center;
            gap: 150px;
            margin-bottom: 20px;
        }
        .apbct_footer_header{
                justify-content: center;
        }
        .apbct_footer_column {
            margin-bottom: 13px;
        }
        .apbct_footer_column h3 {
            margin-bottom: 1em;
            text-align: center;
            color: #3c434a;
        }
        .apbct_footer_links {
            display: flex;
            width: 100%;
            gap: 60px;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .apbct_footer_links li {
                margin-bottom: 0px;
            }
        .apbct_footer_links a {
            color: #3c434a;
        }
        .apbct_footer_links a:hover {
            color: #2271b1;
            text-decoration: underline;
        }
        .apbct_footer_links a i {
            margin-left: 5px
        }
        @media (max-width: 768px) {
            #wpfooter {
                margin-left: 0 !important;
            }
            .apbct_footer {
                flex-direction: column;
                gap: 0px;
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
            .apbct_footer_links li {
                margin-bottom: 10px;
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
            .apbct_footer_column h3 {
                cursor: pointer;
            }
        }
    </style>
    <?php
}
