<?php
/**
 * Demo job listings for bin/seed-demo.php.
 *
 * @return array<int, array<string, mixed>>
 */
function mjb_get_demo_jobs_data() {
    $jobs = array(
        array(
            'title' => 'Senior WordPress Developer',
            'company' => 'Acme Digital',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Engineering',
            'featured' => 1,
            'sections' => array(
                'About the role' => 'Acme Digital builds bespoke WordPress products for publishers and membership organisations. You will lead plugin architecture, mentor mid-level developers, and ship production code weekly.',
                'What you will do' => array(
                    'Design and implement custom plugins with clean PHP, hooks, and REST endpoints.',
                    'Review pull requests and uphold WPCS / PHPCS standards across the team.',
                    'Integrate WooCommerce, membership, and CRM systems for client launches.',
                    'Profile slow queries and optimise caching for high-traffic editorial sites.',
                    'Partner with designers to deliver accessible Gutenberg blocks and patterns.',
                ),
                'Requirements' => array(
                    '5+ years professional WordPress development experience.',
                    'Strong knowledge of PHP 8+, MySQL, and the WordPress data APIs.',
                    'Experience with PHPUnit or similar automated testing.',
                    'Comfortable working async across UK and US time zones.',
                ),
                'Nice to have' => array(
                    'Contributions to open-source WordPress projects.',
                    'Familiarity with WP-CLI and GitHub Actions CI pipelines.',
                ),
                'Benefits' => array(
                    '£65,000–£80,000 depending on experience.',
                    'Fully remote with annual team retreat.',
                    '£1,500 annual learning budget and conference allowance.',
                    '25 days holiday plus public holidays.',
                ),
            ),
        ),
        array(
            'title' => 'Product Designer',
            'company' => 'Northline Studio',
            'location' => 'London',
            'type' => 'Full Time',
            'category' => 'Design',
            'featured' => 1,
            'sections' => array(
                'About the role' => 'Northline Studio is a product design consultancy focused on hiring and HR tech. You will own end-to-end UX for a new employer dashboard used by thousands of recruiters.',
                'What you will do' => array(
                    'Run discovery sessions with employers and translate insights into user journeys.',
                    'Produce wireframes, high-fidelity UI, and interactive Figma prototypes.',
                    'Maintain a design system aligned with WCAG 2.1 AA accessibility targets.',
                    'Collaborate with engineering on feasible component specs and edge cases.',
                    'Present design rationale to stakeholders and incorporate structured feedback.',
                ),
                'Requirements' => array(
                    '4+ years designing B2B SaaS or marketplace products.',
                    'Portfolio demonstrating complex workflows and data-dense interfaces.',
                    'Proficiency in Figma and pragmatic knowledge of HTML/CSS constraints.',
                    'Right to work in the UK.',
                ),
                'Benefits' => array(
                    '£55,000–£68,000 base salary.',
                    'Hybrid working — 2 days per week in Shoreditch studio.',
                    'Private medical cover and enhanced parental leave.',
                    '£800 annual wellness stipend.',
                ),
            ),
        ),
        array(
            'title' => 'Growth Marketing Manager',
            'company' => 'Launchpad Labs',
            'location' => 'San Francisco',
            'type' => 'Contract',
            'category' => 'Marketing',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Launchpad Labs is scaling a WordPress job board plugin from early adopters to mainstream agencies. This six-month contract focuses on measurable pipeline growth.',
                'What you will do' => array(
                    'Own SEO content strategy, keyword research, and on-page optimisation.',
                    'Launch partner campaigns with hosting companies and WordPress agencies.',
                    'Build lifecycle email sequences for trial-to-paid conversion.',
                    'Report weekly on MQLs, organic traffic, and campaign ROI.',
                    'Coordinate webinars and co-marketing with integration partners.',
                ),
                'Requirements' => array(
                    'Proven B2B SaaS or plugin marketing experience.',
                    'Hands-on skills with GA4, Search Console, and marketing automation.',
                    'Excellent writing for technical audiences without jargon overload.',
                    'Available for 6-month contract with potential extension.',
                ),
                'Benefits' => array(
                    '$90–$110 per hour depending on experience.',
                    'Remote-first with quarterly on-site strategy days.',
                    'Direct access to founders and fast decision-making.',
                ),
            ),
        ),
        array(
            'title' => 'Frontend Engineer (React)',
            'company' => 'CloudNine SaaS',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Engineering',
            'featured' => 1,
            'sections' => array(
                'About the role' => 'CloudNine SaaS delivers workforce planning tools to mid-market retailers. Join the team modernising our employer portal with React and a design-system-first approach.',
                'What you will do' => array(
                    'Build responsive React features with TypeScript and tested components.',
                    'Consume REST and GraphQL APIs with robust error and loading states.',
                    'Improve Core Web Vitals on customer-facing scheduling views.',
                    'Participate in architecture discussions and frontend guild sessions.',
                    'Write unit and integration tests with Jest and React Testing Library.',
                ),
                'Requirements' => array(
                    '3+ years production React experience.',
                    'Solid TypeScript and modern CSS (flexbox, grid, custom properties).',
                    'Experience collaborating with backend teams on API contracts.',
                ),
                'Nice to have' => array(
                    'Exposure to Next.js or module federation in micro-frontends.',
                    'Accessibility auditing with axe or similar tooling.',
                ),
                'Benefits' => array(
                    'Competitive salary plus equity grant.',
                    'Remote across EU and UK time zones.',
                    'Home office setup budget of €1,000.',
                ),
            ),
        ),
        array(
            'title' => 'DevOps Engineer',
            'company' => 'Atlas Logistics',
            'location' => 'Manchester',
            'type' => 'Full Time',
            'category' => 'Engineering',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Atlas Logistics operates real-time routing software for European couriers. You will harden our AWS infrastructure and improve deployment safety.',
                'What you will do' => array(
                    'Maintain Terraform modules for ECS, RDS, and CloudFront stacks.',
                    'Build CI/CD pipelines with automated smoke tests on staging.',
                    'Implement observability with structured logs, metrics, and alerting.',
                    'Lead incident response and publish blameless post-mortems.',
                    'Partner with security on secrets rotation and least-privilege IAM.',
                ),
                'Requirements' => array(
                    'Experience running production workloads on AWS.',
                    'Proficiency with Docker, Terraform, and GitHub Actions or GitLab CI.',
                    'Comfortable with on-call rotation (paid allowance).',
                ),
                'Benefits' => array(
                    '£58,000–£72,000 plus annual bonus.',
                    'Hybrid — 3 days in Manchester office.',
                    'Training budget for cloud certifications.',
                ),
            ),
        ),
        array(
            'title' => 'UX Researcher',
            'company' => 'Harbor Health',
            'location' => 'New York',
            'type' => 'Full Time',
            'category' => 'Design',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Harbor Health is a digital clinic platform serving patients in underserved communities. Research you conduct will directly shape appointment booking and telehealth flows.',
                'What you will do' => array(
                    'Plan and moderate usability studies with patients and clinicians.',
                    'Synthesise findings into actionable recommendations for product squads.',
                    'Maintain a research repository and participant consent workflows.',
                    'Run surveys and diary studies to validate new feature concepts.',
                    'Advocate for inclusive research methods and diverse participant panels.',
                ),
                'Requirements' => array(
                    '3+ years UX research in health, fintech, or regulated industries.',
                    'Mixed-methods expertise — interviews, usability tests, and quant surveys.',
                    'Strong written communication for executive-ready insight summaries.',
                ),
                'Benefits' => array(
                    '$105,000–$125,000 base salary.',
                    'Comprehensive health, dental, and vision coverage.',
                    '401(k) match and 20 days PTO.',
                ),
            ),
        ),
        array(
            'title' => 'Content Marketing Specialist',
            'company' => 'Summit Education',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Marketing',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Summit Education helps professionals upskill through cohort-based courses. You will own the editorial calendar for blog, newsletter, and social channels.',
                'What you will do' => array(
                    'Write long-form guides, case studies, and course launch copy.',
                    'Repurpose webinars into SEO articles and downloadable resources.',
                    'Manage freelance writers and enforce style and fact-check standards.',
                    'Track content performance and iterate headlines and CTAs.',
                    'Collaborate with product marketing on landing page messaging.',
                ),
                'Requirements' => array(
                    '2+ years B2B or edtech content marketing experience.',
                    'Portfolio of published articles with measurable traffic or leads.',
                    'Working knowledge of WordPress block editor and basic on-page SEO.',
                ),
                'Benefits' => array(
                    '£38,000–£46,000 salary.',
                    'Fully remote UK/EU.',
                    'Free access to all Summit courses.',
                ),
            ),
        ),
        array(
            'title' => 'Account Executive',
            'company' => 'Greenfield Finance',
            'location' => 'London',
            'type' => 'Full Time',
            'category' => 'Sales',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Greenfield Finance provides lending APIs to growing e-commerce brands. You will manage a portfolio of mid-market accounts and exceed quarterly ARR targets.',
                'What you will do' => array(
                    'Run full sales cycle from outbound prospecting to signed contracts.',
                    'Deliver product demos tailored to CFO and operations stakeholders.',
                    'Negotiate pricing within approved guardrails and coordinate legal review.',
                    'Maintain accurate pipeline data in HubSpot.',
                    'Feed customer objections back to product and marketing teams.',
                ),
                'Requirements' => array(
                    '2+ years closing experience in fintech or B2B SaaS.',
                    'Track record meeting or exceeding quota.',
                    'Excellent presentation and discovery skills.',
                ),
                'Benefits' => array(
                    '£45,000 base plus uncapped commission (OTE £85,000+).',
                    'Central London office with flexible hybrid policy.',
                    'Share options after 12 months.',
                ),
            ),
        ),
        array(
            'title' => 'Customer Success Manager',
            'company' => 'Pixel & Ink',
            'location' => 'Austin',
            'type' => 'Full Time',
            'category' => 'Customer Success',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Pixel & Ink sells creative automation software to in-house brand teams. You will ensure customers onboard successfully and expand usage across departments.',
                'What you will do' => array(
                    'Own a book of 40–60 accounts from onboarding through renewal.',
                    'Run quarterly business reviews with clear success metrics.',
                    'Identify upsell opportunities and partner with account executives.',
                    'Create help-centre articles and short Loom walkthroughs.',
                    'Escalate bugs with reproducible steps and business impact context.',
                ),
                'Requirements' => array(
                    '2+ years CSM experience in creative, martech, or SaaS tools.',
                    'Empathetic communicator who can simplify technical concepts.',
                    'Comfortable with Gainsight or similar CS platforms.',
                ),
                'Benefits' => array(
                    '$70,000–$85,000 plus performance bonus.',
                    'Hybrid Austin office or fully remote within Texas.',
                    'Health insurance and 15 days PTO.',
                ),
            ),
        ),
        array(
            'title' => 'Data Analyst',
            'company' => 'Velocity Motors',
            'location' => 'Berlin',
            'type' => 'Full Time',
            'category' => 'Data',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Velocity Motors is a used-vehicle marketplace expanding across the DACH region. You will turn product and marketing data into decisions leadership trusts.',
                'What you will do' => array(
                    'Build dashboards in Looker or Metabase for funnel and inventory KPIs.',
                    'Write SQL queries against our Snowflake warehouse.',
                    'Design A/B test analyses with clear statistical guardrails.',
                    'Partner with engineers on event tracking quality and documentation.',
                    'Present monthly insights to product and growth leadership.',
                ),
                'Requirements' => array(
                    '2+ years analytics experience in marketplace or e-commerce.',
                    'Advanced SQL and spreadsheet modelling skills.',
                    'Fluent English; German is a plus.',
                ),
                'Benefits' => array(
                    '€52,000–€62,000 depending on experience.',
                    'Office in Berlin Mitte with remote flexibility.',
                    'Job ticket and gym subsidy.',
                ),
            ),
        ),
        array(
            'title' => 'Product Manager',
            'company' => 'Riverstone HR',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Product',
            'featured' => 1,
            'sections' => array(
                'About the role' => 'Riverstone HR builds applicant tracking for SMB recruiters. You will own the candidate experience roadmap from application to offer.',
                'What you will do' => array(
                    'Prioritise backlog items using impact, effort, and customer evidence.',
                    'Write PRDs with acceptance criteria designers and engineers can ship against.',
                    'Interview customers weekly and synthesise themes into quarterly OKRs.',
                    'Coordinate beta programmes and measure adoption of new workflows.',
                    'Partner with marketing on launch positioning and enablement assets.',
                ),
                'Requirements' => array(
                    '4+ years product management in HR tech or workflow SaaS.',
                    'Strong analytical skills and comfort with SQL or product analytics tools.',
                    'Excellent written communication for async remote teams.',
                ),
                'Benefits' => array(
                    '£70,000–£85,000 plus equity.',
                    'Fully remote across UK and Ireland.',
                    'Annual company off-site and home office stipend.',
                ),
            ),
        ),
        array(
            'title' => 'Technical SEO Consultant',
            'company' => 'Brightwave Agency',
            'location' => 'Remote',
            'type' => 'Contract',
            'category' => 'Marketing',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Brightwave Agency supports enterprise publishers migrating to headless and WordPress hybrid stacks. This contract focuses on crawlability, indexation, and Core Web Vitals.',
                'What you will do' => array(
                    'Audit site architecture, internal linking, and XML sitemap health.',
                    'Recommend fixes for JavaScript rendering and canonicalisation issues.',
                    'Work with developers on schema markup and hreflang implementations.',
                    'Monitor rankings and log file data after major releases.',
                    'Deliver monthly technical SEO reports with prioritised action lists.',
                ),
                'Requirements' => array(
                    '5+ years technical SEO for large content sites.',
                    'Hands-on experience with Screaming Frog, Sitebulb, and GSC.',
                    'Ability to read HTML, robots.txt, and server response codes.',
                ),
                'Benefits' => array(
                    'Day rate £450–£550.',
                    'Fully remote, flexible hours.',
                    'Potential for ongoing retainer after initial 3-month project.',
                ),
            ),
        ),
        array(
            'title' => 'HR Business Partner',
            'company' => 'Oak & Co.',
            'location' => 'London',
            'type' => 'Full Time',
            'category' => 'HR',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Oak & Co. is a 120-person professional services firm scaling its UK practice. You will partner with practice leads on hiring, performance, and employee relations.',
                'What you will do' => array(
                    'Advise managers on grievances, disciplinaries, and policy interpretation.',
                    'Support workforce planning and structured interview programmes.',
                    'Drive engagement initiatives and analyse pulse survey results.',
                    'Ensure HR processes comply with UK employment legislation.',
                    'Coach people managers on feedback and development conversations.',
                ),
                'Requirements' => array(
                    'CIPD Level 5 qualified or equivalent experience.',
                    '3+ years HRBP experience in professional services or consulting.',
                    'Discreet, pragmatic, and confident with difficult conversations.',
                ),
                'Benefits' => array(
                    '£48,000–£56,000 salary.',
                    '25 days holiday plus office closure at Christmas.',
                    'Pension contribution and private healthcare.',
                ),
            ),
        ),
        array(
            'title' => 'Legal Counsel (Commercial)',
            'company' => 'Nexus Payments',
            'location' => 'Toronto',
            'type' => 'Full Time',
            'category' => 'Legal',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Nexus Payments processes card transactions for Canadian SMBs. You will be the second lawyer on the team, focusing on vendor contracts and regulatory inquiries.',
                'What you will do' => array(
                    'Draft and negotiate SaaS, partnership, and procurement agreements.',
                    'Review marketing claims and customer-facing terms for compliance risk.',
                    'Support privacy programme activities under PIPEDA and provincial rules.',
                    'Manage outside counsel on litigation and specialised matters.',
                    'Build self-serve contract templates for sales and finance teams.',
                ),
                'Requirements' => array(
                    'Licensed to practise law in Ontario (or eligible for transfer).',
                    '4+ years commercial contracts experience, ideally in fintech.',
                    'Clear, concise drafting style for non-lawyer stakeholders.',
                ),
                'Benefits' => array(
                    'CAD $130,000–$155,000 base.',
                    'Hybrid Toronto office.',
                    'Extended health benefits and wellness days.',
                ),
            ),
        ),
        array(
            'title' => 'Mobile Developer (iOS)',
            'company' => 'Trailhead Outdoors',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Engineering',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Trailhead Outdoors helps hikers discover routes offline. You will own the iOS app experience including maps, GPX import, and subscription paywalls.',
                'What you will do' => array(
                    'Ship SwiftUI features with thoughtful offline-first architecture.',
                    'Integrate MapKit and custom tile caching for remote areas.',
                    'Collaborate with Android and backend engineers on shared APIs.',
                    'Maintain App Store listings, release notes, and crash-free session targets.',
                    'Write XCTest coverage for critical navigation and sync flows.',
                ),
                'Requirements' => array(
                    '3+ years iOS development with Swift.',
                    'Published apps on the App Store.',
                    'Understanding of Core Data or SQLite persistence patterns.',
                ),
                'Benefits' => array(
                    '£55,000–£70,000 depending on experience.',
                    'Remote across UK/EU.',
                    'Annual gear allowance for field testing.',
                ),
            ),
        ),
        array(
            'title' => 'Office Manager',
            'company' => 'Cedar Workspace',
            'location' => 'Manchester',
            'type' => 'Part Time',
            'category' => 'Operations',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Cedar Workspace runs flexible offices for startups in Manchester city centre. This part-time role keeps daily operations smooth for 200+ members.',
                'What you will do' => array(
                    'Welcome members, manage front-desk enquiries, and coordinate mail.',
                    'Schedule meeting rooms and ensure AV equipment is ready.',
                    'Order supplies, liaise with cleaners, and track facilities tickets.',
                    'Support event setup for member workshops and investor evenings.',
                    'Maintain health and safety checklists and visitor sign-in records.',
                ),
                'Requirements' => array(
                    '2+ years office, facilities, or hospitality coordination experience.',
                    'Friendly, organised, and calm under pressure.',
                    'Available 24 hours per week across weekday mornings.',
                ),
                'Benefits' => array(
                    '£14–£16 per hour.',
                    'Free coworking membership on non-working days.',
                    'Training in facilities and community management.',
                ),
            ),
        ),
        array(
            'title' => 'QA Engineer',
            'company' => 'Signal Security',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Engineering',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Signal Security provides vulnerability management for mid-market IT teams. Quality assurance is critical before every release touches customer infrastructure.',
                'What you will do' => array(
                    'Design test plans for web app, API, and agent-based scanning features.',
                    'Build automated regression suites with Playwright or Cypress.',
                    'Perform exploratory testing on security-sensitive workflows.',
                    'Log reproducible defects with severity, environment, and attachments.',
                    'Verify fixes and participate in release go/no-go meetings.',
                ),
                'Requirements' => array(
                    '3+ years QA experience in B2B SaaS.',
                    'Hands-on test automation skills beyond manual checklists.',
                    'Basic understanding of OWASP Top 10 vulnerabilities.',
                ),
                'Benefits' => array(
                    '£42,000–£52,000 salary.',
                    'Remote UK with optional quarterly team days.',
                    'Certification budget for ISTQB or security courses.',
                ),
            ),
        ),
        array(
            'title' => 'Social Media Manager',
            'company' => 'Bloom Botanicals',
            'location' => 'Sydney',
            'type' => 'Part Time',
            'category' => 'Marketing',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Bloom Botanicals sells sustainable home and garden products across Australia. You will grow brand awareness on Instagram, TikTok, and Pinterest.',
                'What you will do' => array(
                    'Plan and publish 12–15 posts per week aligned with seasonal campaigns.',
                    'Shoot short-form video and lifestyle photography in our Surry Hills studio.',
                    'Engage with community comments and influencer partnerships.',
                    'Report on reach, engagement, and attributed site traffic weekly.',
                    'Coordinate giveaways and UGC programmes with the e-commerce team.',
                ),
                'Requirements' => array(
                    '2+ years social media for consumer brands.',
                    'Strong visual taste and basic photo/video editing skills.',
                    'Based in Sydney for periodic studio shoots.',
                ),
                'Benefits' => array(
                    'AUD $45–$55 per hour, 20 hours per week.',
                    'Product discounts and flexible scheduling.',
                ),
            ),
        ),
        array(
            'title' => 'Machine Learning Engineer',
            'company' => 'Prism Analytics',
            'location' => 'San Francisco',
            'type' => 'Full Time',
            'category' => 'Data',
            'featured' => 1,
            'sections' => array(
                'About the role' => 'Prism Analytics builds forecasting models for retail inventory teams. You will productionise ML pipelines that run reliably at scale.',
                'What you will do' => array(
                    'Train and evaluate demand-forecasting models on noisy retail datasets.',
                    'Deploy models via batch and near-real-time inference services.',
                    'Monitor drift, data quality, and model performance in production.',
                    'Collaborate with data engineers on feature stores and labelling workflows.',
                    'Document experiments and present trade-offs to product stakeholders.',
                ),
                'Requirements' => array(
                    'MS or PhD in CS, statistics, or equivalent industry experience.',
                    'Proficiency in Python, scikit-learn, and PyTorch or TensorFlow.',
                    'Experience shipping ML to production, not just notebooks.',
                ),
                'Benefits' => array(
                    '$150,000–$185,000 base plus equity.',
                    'Hybrid SF office or remote within US time zones.',
                    'Conference and GPU cloud compute budget.',
                ),
            ),
        ),
        array(
            'title' => 'Implementation Specialist',
            'company' => 'Workstream ATS',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Customer Success',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Workstream ATS onboard enterprise recruiting teams onto our platform. Implementation specialists ensure go-live dates are met with clean data migrations.',
                'What you will do' => array(
                    'Run kickoff calls and document customer configuration requirements.',
                    'Map legacy ATS fields to Workstream schemas and validate imports.',
                    'Train recruiters and hiring managers on workflows and permissions.',
                    'Manage multiple implementations concurrently with clear project plans.',
                    'Hand off stable accounts to customer success with structured notes.',
                ),
                'Requirements' => array(
                    '2+ years SaaS implementation or solutions consulting experience.',
                    'HR or recruiting domain knowledge strongly preferred.',
                    'Excellent project management and stakeholder communication.',
                ),
                'Benefits' => array(
                    '£40,000–£50,000 plus quarterly bonus.',
                    'Remote UK with travel 2–3 days per month.',
                    'Clear progression path into senior implementation or CS roles.',
                ),
            ),
        ),
        array(
            'title' => 'Brand Designer',
            'company' => 'Lumen Coffee',
            'location' => 'Portland',
            'type' => 'Contract',
            'category' => 'Design',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Lumen Coffee is refreshing its brand ahead of national retail expansion. This three-month contract covers packaging, signage, and digital templates.',
                'What you will do' => array(
                    'Evolve logo usage, colour palette, and typography guidelines.',
                    'Design bag labels, cup sleeves, and shelf talkers for retail partners.',
                    'Create Figma libraries for social templates and email headers.',
                    'Prepare print-ready files and liaise with production vendors.',
                    'Present concepts to leadership and incorporate feedback iteratively.',
                ),
                'Requirements' => array(
                    'Strong portfolio in CPG or hospitality branding.',
                    'Expertise in Illustrator, InDesign, and Figma.',
                    'Experience managing colour proofing for physical packaging.',
                ),
                'Benefits' => array(
                    '$65–$85 per hour for ~30 hours per week.',
                    'Remote with two on-site tasting sessions in Portland.',
                ),
            ),
        ),
        array(
            'title' => 'Support Engineer',
            'company' => 'Stacklayer Hosting',
            'location' => 'Remote',
            'type' => 'Full Time',
            'category' => 'Customer Success',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Stacklayer Hosting provides managed WordPress infrastructure for agencies. Support engineers troubleshoot sites under real customer pressure.',
                'What you will do' => array(
                    'Respond to tickets about performance, DNS, SSL, and plugin conflicts.',
                    'Use SSH, WP-CLI, and error logs to diagnose production issues.',
                    'Document recurring fixes in the internal knowledge base.',
                    'Escalate platform bugs with replication steps to engineering.',
                    'Participate in weekend on-call rotation (compensated).',
                ),
                'Requirements' => array(
                    '2+ years WordPress or Linux hosting support experience.',
                    'Comfortable reading PHP error logs and MySQL slow query output.',
                    'Patient communicator for non-technical agency owners.',
                ),
                'Benefits' => array(
                    '£32,000–£40,000 depending on shift pattern.',
                    'Fully remote UK.',
                    'Free hosting for personal projects.',
                ),
            ),
        ),
        array(
            'title' => 'Finance Manager',
            'company' => 'Evergreen Renewables',
            'location' => 'Dublin',
            'type' => 'Full Time',
            'category' => 'Operations',
            'featured' => 0,
            'sections' => array(
                'About the role' => 'Evergreen Renewables develops solar projects across Ireland. You will oversee management accounts, cash flow, and board reporting.',
                'What you will do' => array(
                    'Produce monthly P&amp;L, balance sheet, and cash-flow packs.',
                    'Manage AP/AR cycles and supplier payment runs.',
                    'Support budget planning and project-level cost tracking.',
                    'Coordinate year-end audit preparation with external accountants.',
                    'Implement process improvements as the team grows past 80 people.',
                ),
                'Requirements' => array(
                    'ACCA, CPA, or CIMA qualified (or final-stage student).',
                    '5+ years finance experience in construction, energy, or infrastructure.',
                    'Advanced Excel and ERP familiarity (NetSuite experience a plus).',
                ),
                'Benefits' => array(
                    '€65,000–€78,000 depending on qualification level.',
                    'Hybrid Dublin office.',
                    'Electric vehicle scheme and pension matching.',
                ),
            ),
        ),
    );

    foreach ($jobs as &$job) {
        $job['content'] = mjb_build_demo_job_content($job['sections']);
        unset($job['sections']);
    }
    unset($job);

    return $jobs;
}

/**
 * @param array<string, string|array<int, string>> $sections
 * @return string
 */
function mjb_build_demo_job_content($sections) {
    $html = '';

    foreach ($sections as $heading => $body) {
        $html .= '<h3>' . esc_html($heading) . '</h3>';

        if (is_array($body)) {
            $html .= '<ul>';
            foreach ($body as $item) {
                $html .= '<li>' . esc_html($item) . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>' . esc_html($body) . '</p>';
        }
    }

    return $html;
}

/**
 * Demo company profiles for hover previews and company pages.
 *
 * Keys must match `company` names in mjb_get_demo_jobs_data().
 *
 * @return array<string, array{tagline:string,website:string,linkedin:string,twitter:string,about:string}>
 */
function mjb_get_demo_companies_data() {
    return array(
        'Acme Digital' => array(
            'tagline' => 'WordPress products for publishers and memberships.',
            'website' => 'https://www.acmedigital.example',
            'linkedin' => 'https://www.linkedin.com/company/acme-digital',
            'twitter' => 'https://x.com/acmedigital',
            'about' => 'Acme Digital builds bespoke WordPress products for publishers and membership organisations.',
        ),
        'Northline Studio' => array(
            'tagline' => 'Product design for ambitious SaaS teams.',
            'website' => 'https://www.northlinestudio.example',
            'linkedin' => 'https://www.linkedin.com/company/northline-studio',
            'twitter' => 'https://x.com/northlinestudio',
            'about' => 'Northline Studio is a London product-design studio working with SaaS and marketplace teams.',
        ),
        'Launchpad Labs' => array(
            'tagline' => 'Early-stage product, shipped weekly.',
            'website' => 'https://www.launchpadlabs.example',
            'linkedin' => 'https://www.linkedin.com/company/launchpad-labs',
            'twitter' => 'https://x.com/launchpadlabs',
            'about' => 'Launchpad Labs helps founders go from prototype to production with a small, senior build team.',
        ),
        'CloudNine SaaS' => array(
            'tagline' => 'B2B software that stays out of the way.',
            'website' => 'https://www.cloudninesaas.example',
            'linkedin' => 'https://www.linkedin.com/company/cloudnine-saas',
            'twitter' => 'https://x.com/cloudninesaas',
            'about' => 'CloudNine SaaS builds subscription operations software for mid-market teams.',
        ),
        'Atlas Logistics' => array(
            'tagline' => 'Freight visibility from dock to door.',
            'website' => 'https://www.atlaslogistics.example',
            'linkedin' => 'https://www.linkedin.com/company/atlas-logistics',
            'twitter' => 'https://x.com/atlaslogistics',
            'about' => 'Atlas Logistics runs regional freight and last-mile operations with a strong ops culture.',
        ),
        'Harbor Health' => array(
            'tagline' => 'Clinician-led digital health tools.',
            'website' => 'https://www.harborhealth.example',
            'linkedin' => 'https://www.linkedin.com/company/harbor-health',
            'twitter' => 'https://x.com/harborhealth',
            'about' => 'Harbor Health builds software used by clinics to coordinate care and patient communications.',
        ),
        'Summit Education' => array(
            'tagline' => 'Cohort courses for working professionals.',
            'website' => 'https://www.summiteducation.example',
            'linkedin' => 'https://www.linkedin.com/company/summit-education',
            'twitter' => 'https://x.com/summitedu',
            'about' => 'Summit Education runs live, cohort-based courses for people upskilling in their careers.',
        ),
        'Greenfield Finance' => array(
            'tagline' => 'Clearer books for growing companies.',
            'website' => 'https://www.greenfieldfinance.example',
            'linkedin' => 'https://www.linkedin.com/company/greenfield-finance',
            'twitter' => 'https://x.com/greenfieldfin',
            'about' => 'Greenfield Finance provides fractional finance leadership and bookkeeping for SMEs.',
        ),
        'Pixel & Ink' => array(
            'tagline' => 'Brand, editorial, and campaign design.',
            'website' => 'https://www.pixelandink.example',
            'linkedin' => 'https://www.linkedin.com/company/pixel-and-ink',
            'twitter' => 'https://x.com/pixelandink',
            'about' => 'Pixel & Ink is an independent studio for brand systems, editorial design, and campaigns.',
        ),
        'Velocity Motors' => array(
            'tagline' => 'Aftersales software for motor groups.',
            'website' => 'https://www.velocitymotors.example',
            'linkedin' => 'https://www.linkedin.com/company/velocity-motors',
            'twitter' => 'https://x.com/velocitymotors',
            'about' => 'Velocity Motors builds workshop and aftersales tools for multi-site motor groups.',
        ),
        'Riverstone HR' => array(
            'tagline' => 'People operations without the paperwork fog.',
            'website' => 'https://www.riverstonehr.example',
            'linkedin' => 'https://www.linkedin.com/company/riverstone-hr',
            'twitter' => 'https://x.com/riverstonehr',
            'about' => 'Riverstone HR advises growing teams on hiring, policy, and people operations.',
        ),
        'Brightwave Agency' => array(
            'tagline' => 'Performance marketing with a point of view.',
            'website' => 'https://www.brightwaveagency.example',
            'linkedin' => 'https://www.linkedin.com/company/brightwave-agency',
            'twitter' => 'https://x.com/brightwavehq',
            'about' => 'Brightwave Agency is a performance and brand studio for consumer and B2B clients.',
        ),
        'Oak & Co.' => array(
            'tagline' => 'Workplace furniture made to last.',
            'website' => 'https://www.oakandco.example',
            'linkedin' => 'https://www.linkedin.com/company/oak-and-co',
            'twitter' => 'https://x.com/oakandco',
            'about' => 'Oak & Co. designs and supplies durable workplace furniture for studios and offices.',
        ),
        'Nexus Payments' => array(
            'tagline' => 'Card processing for Canadian SMBs.',
            'website' => 'https://www.nexuspayments.example',
            'linkedin' => 'https://www.linkedin.com/company/nexus-payments',
            'twitter' => 'https://x.com/nexuspayments',
            'about' => 'Nexus Payments processes card transactions and payouts for small Canadian businesses.',
        ),
        'Trailhead Outdoors' => array(
            'tagline' => 'Gear for people who actually go outside.',
            'website' => 'https://www.trailheadoutdoors.example',
            'linkedin' => 'https://www.linkedin.com/company/trailhead-outdoors',
            'twitter' => 'https://x.com/trailheadgear',
            'about' => 'Trailhead Outdoors makes and sells outdoor gear with a small, product-led team.',
        ),
        'Cedar Workspace' => array(
            'tagline' => 'Flexible studios for hybrid teams.',
            'website' => 'https://www.cedarworkspace.example',
            'linkedin' => 'https://www.linkedin.com/company/cedar-workspace',
            'twitter' => 'https://x.com/cedarworkspace',
            'about' => 'Cedar Workspace operates flexible studio and office space for hybrid teams.',
        ),
        'Signal Security' => array(
            'tagline' => 'Practical security for product companies.',
            'website' => 'https://www.signalsecurity.example',
            'linkedin' => 'https://www.linkedin.com/company/signal-security',
            'twitter' => 'https://x.com/signalsec',
            'about' => 'Signal Security helps product companies run sensible application and cloud security.',
        ),
        'Bloom Botanicals' => array(
            'tagline' => 'Plants and florals, delivered with care.',
            'website' => 'https://www.bloombotanicals.example',
            'linkedin' => 'https://www.linkedin.com/company/bloom-botanicals',
            'twitter' => 'https://x.com/bloombotanicals',
            'about' => 'Bloom Botanicals grows and delivers plants and seasonal florals for homes and workplaces.',
        ),
        'Prism Analytics' => array(
            'tagline' => 'Analytics that product teams will actually use.',
            'website' => 'https://www.prismanalytics.example',
            'linkedin' => 'https://www.linkedin.com/company/prism-analytics',
            'twitter' => 'https://x.com/prismanalytics',
            'about' => 'Prism Analytics builds event pipelines and dashboards for product and growth teams.',
        ),
        'Workstream ATS' => array(
            'tagline' => 'Applicant tracking without the bloat.',
            'website' => 'https://www.workstreamats.example',
            'linkedin' => 'https://www.linkedin.com/company/workstream-ats',
            'twitter' => 'https://x.com/workstreamats',
            'about' => 'Workstream ATS is a focused applicant-tracking product for in-house recruiting teams.',
        ),
        'Lumen Coffee' => array(
            'tagline' => 'Roastery and cafe, wholesale and retail.',
            'website' => 'https://www.lumencoffee.example',
            'linkedin' => 'https://www.linkedin.com/company/lumen-coffee',
            'twitter' => 'https://x.com/lumencoffee',
            'about' => 'Lumen Coffee roasts and serves specialty coffee, with wholesale accounts across the city.',
        ),
        'Stacklayer Hosting' => array(
            'tagline' => 'Managed hosting for WordPress at scale.',
            'website' => 'https://www.stacklayerhosting.example',
            'linkedin' => 'https://www.linkedin.com/company/stacklayer-hosting',
            'twitter' => 'https://x.com/stacklayer',
            'about' => 'Stacklayer Hosting provides managed WordPress hosting and on-call support for agencies.',
        ),
        'Evergreen Renewables' => array(
            'tagline' => 'Community-scale solar, built to last.',
            'website' => 'https://www.evergreenrenewables.example',
            'linkedin' => 'https://www.linkedin.com/company/evergreen-renewables',
            'twitter' => 'https://x.com/evergreenre',
            'about' => 'Evergreen Renewables develops solar projects and energy infrastructure across Ireland.',
        ),
    );
}