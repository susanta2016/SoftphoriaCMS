<?php

/*
|--------------------------------------------------------------------------
| Starter services
|--------------------------------------------------------------------------
|
| The six services the homepage has always listed, as real records with
| starter copy for their detail pages. Used by HomePageSeeder (fresh
| installs) and by the 2026_09_26_150100 data migration (existing sites,
| which keep whatever card titles/descriptions/icons an admin already set).
| Plain, generic descriptions of the work — no invented clients, numbers
| or claims — meant to be edited in Admin → Services.
|
*/

return [
    [
        'title' => 'Web Development',
        'slug' => 'web-development',
        'icon' => 'monitor',
        'tagline' => 'Fast, accessible websites that turn visitors into customers.',
        'summary' => 'Modern, responsive and high-performance websites tailored to your business goals.',
        'technologies' => ['Laravel', 'PHP', 'Next.js', 'Tailwind CSS', 'MySQL'],
        'body' => '<p>Your website is often the first conversation a customer has with your business. We build sites that load quickly, work beautifully on every device and are easy for your team to keep up to date.</p><h2>How we approach it</h2><p>We start from your goals — leads, sales, sign-ups or support — and design the structure and content around them. Every build is responsive, accessible and search-engine friendly from day one, with analytics in place so you can see what is working.</p><h2>Built to last</h2><p>We use proven, well-supported technologies and write clean, documented code, so your site is secure, maintainable and ready to grow with you.</p>',
        'highlights' => [
            ['title' => 'Responsive design', 'description' => 'Layouts that look and work great on phones, tablets and desktops.'],
            ['title' => 'Performance & SEO', 'description' => 'Fast page loads, clean markup and technical SEO built in.'],
            ['title' => 'Easy content editing', 'description' => 'An admin panel your team can use without calling a developer.'],
            ['title' => 'Security & care', 'description' => 'HTTPS, updates, backups and monitoring after launch.'],
        ],
        'faqs' => [
            ['question' => 'How long does a website project take?', 'answer' => 'It depends on scope. A focused marketing site is usually a matter of weeks; larger sites with custom features take longer. We agree a timeline with you before any work starts.'],
            ['question' => 'Can you redesign my existing website?', 'answer' => 'Yes. We can refresh the design, rebuild it on a modern stack, or improve performance and SEO while keeping your existing content.'],
            ['question' => 'Will I be able to update the content myself?', 'answer' => 'Yes. Every site we build includes an easy-to-use admin area for pages, images and other content.'],
        ],
    ],
    [
        'title' => 'Custom Software',
        'slug' => 'custom-software',
        'icon' => 'code',
        'tagline' => 'Software shaped around the way your business actually works.',
        'summary' => 'Business applications designed around your specific workflow and requirements.',
        'technologies' => ['Laravel', 'Python', 'Django', 'Node.js', 'PostgreSQL'],
        'body' => '<p>Off-the-shelf tools rarely fit perfectly. Custom software lets you automate the processes that make your business unique, instead of bending your team around someone else\'s product.</p><h2>From idea to working product</h2><p>We work with you to understand the workflow, map the requirements and plan a first release that delivers value quickly. From there we iterate in short cycles, so you see progress and can steer priorities as you learn.</p><h2>Reliable foundations</h2><p>Role-based access, audit trails, automated tests and clear documentation come as standard, so the software stays dependable as it grows.</p>',
        'highlights' => [
            ['title' => 'Discovery & planning', 'description' => 'Requirements, user flows and a clear, phased delivery plan.'],
            ['title' => 'Web & business apps', 'description' => 'Portals, dashboards, internal tools and customer-facing apps.'],
            ['title' => 'Automation', 'description' => 'Replace manual, repetitive work with reliable automated workflows.'],
            ['title' => 'Ongoing development', 'description' => 'Support, improvements and new features after launch.'],
        ],
        'faqs' => [
            ['question' => 'Do I own the code you build?', 'answer' => 'Ownership terms are agreed in the contract before the project starts; our standard arrangement is that you own the application built for you.'],
            ['question' => 'Can you take over an existing application?', 'answer' => 'Yes. We start with a code and infrastructure review, then fix the most important issues and continue development.'],
            ['question' => 'How do you estimate the cost?', 'answer' => 'After a short discovery phase we break the work into features and give you a clear estimate per phase, so there are no surprises.'],
        ],
    ],
    [
        'title' => 'E-Commerce Solutions',
        'slug' => 'e-commerce-solutions',
        'icon' => 'cart',
        'tagline' => 'Online stores that sell more and run themselves.',
        'summary' => 'Scalable e-commerce platforms with integrations and automation.',
        'technologies' => ['Shopify', 'Laravel', 'Stripe', 'MySQL', 'AWS'],
        'body' => '<p>A great online store is more than a product catalogue. It needs smooth checkout, accurate stock, reliable payments and the integrations that keep orders flowing without manual work.</p><h2>Stores that convert</h2><p>We design shopping experiences that are fast and easy to use, with clear product pages, search and filtering, and a checkout that removes friction.</p><h2>Connected to your business</h2><p>We connect your store to payment providers, shipping, accounting, ERP and marketing tools, so orders, stock and customer data stay in sync automatically.</p>',
        'highlights' => [
            ['title' => 'Custom or platform stores', 'description' => 'Shopify, custom builds, or a mix — whatever fits your model.'],
            ['title' => 'B2B & B2C', 'description' => 'Customer-specific pricing, quotes, bulk ordering and more.'],
            ['title' => 'Payments & shipping', 'description' => 'Secure payment gateways and shipping integrations.'],
            ['title' => 'Integrations & automation', 'description' => 'ERP, inventory, accounting and marketing kept in sync.'],
        ],
        'faqs' => [
            ['question' => 'Should I use Shopify or a custom platform?', 'answer' => 'Shopify is ideal for many stores; complex pricing, B2B workflows or unusual integrations can justify a custom build. We will recommend the option that fits your needs and budget.'],
            ['question' => 'Can you migrate my existing store?', 'answer' => 'Yes. We migrate products, customers and orders and set up redirects to protect your search rankings.'],
            ['question' => 'Which payment providers do you support?', 'answer' => 'We commonly work with Stripe and other major gateways, and can integrate the provider you already use.'],
        ],
    ],
    [
        'title' => 'Cloud & DevOps',
        'slug' => 'cloud-devops',
        'icon' => 'cloud',
        'tagline' => 'Infrastructure that is secure, scalable and cost-efficient.',
        'summary' => 'AWS infrastructure, migration, monitoring, security and performance optimization.',
        'technologies' => ['AWS', 'Docker', 'Kubernetes', 'NGINX', 'Terraform'],
        'body' => '<p>Your applications are only as reliable as the infrastructure they run on. We design, migrate and operate cloud environments that stay fast under load, recover quickly and do not waste money.</p><h2>Migration and modernization</h2><p>We plan migrations in small, reversible steps, containerize applications where it helps, and move workloads to the cloud with minimal downtime.</p><h2>Automation and visibility</h2><p>Automated deployments, infrastructure as code, monitoring and alerting mean releases are routine and problems are spotted before your users notice them.</p>',
        'highlights' => [
            ['title' => 'Cloud architecture', 'description' => 'Well-designed AWS environments sized for your workload.'],
            ['title' => 'Migration', 'description' => 'Move existing applications to the cloud with minimal downtime.'],
            ['title' => 'CI/CD & automation', 'description' => 'Automated build, test and deployment pipelines.'],
            ['title' => 'Monitoring & security', 'description' => 'Alerting, backups, hardening and cost optimization.'],
        ],
        'faqs' => [
            ['question' => 'Can you reduce our cloud bill?', 'answer' => 'Often, yes. A review of instance sizes, storage, reserved capacity and unused resources usually uncovers savings.'],
            ['question' => 'Do you provide ongoing support?', 'answer' => 'Yes. We can monitor and maintain your infrastructure after a project, or act as your on-call DevOps team.'],
            ['question' => 'Do you only work with AWS?', 'answer' => 'AWS is our main platform, but containerized workloads and many of our practices apply to other providers too.'],
        ],
    ],
    [
        'title' => 'API & System Integrations',
        'slug' => 'api-system-integrations',
        'icon' => 'nodes',
        'tagline' => 'Make your systems talk to each other — reliably.',
        'summary' => 'Connect your website, applications, ERP, CRM and third-party services.',
        'technologies' => ['REST', 'Laravel', 'Python', 'Webhooks', 'Redis'],
        'body' => '<p>Data trapped in separate systems means double entry, mistakes and slow decisions. We connect your applications so information flows automatically and accurately between them.</p><h2>Robust by design</h2><p>Integrations fail in the real world — APIs time out and data arrives in unexpected shapes. We build with retries, queues, validation and logging so problems are handled gracefully and are easy to trace.</p><h2>APIs for your own products</h2><p>We also design and build well-documented APIs so partners, mobile apps and other systems can work with your platform securely.</p>',
        'highlights' => [
            ['title' => 'Third-party integrations', 'description' => 'ERP, CRM, payment, shipping and marketing platforms.'],
            ['title' => 'Custom API development', 'description' => 'Secure, documented REST APIs for your products.'],
            ['title' => 'Data synchronization', 'description' => 'Keep records consistent across all your systems.'],
            ['title' => 'Monitoring & reliability', 'description' => 'Queues, retries and alerts so nothing silently fails.'],
        ],
        'faqs' => [
            ['question' => 'Can you integrate with a system that has no API?', 'answer' => 'Often, yes — through file exports, database access or other supported methods. We will assess the safest option.'],
            ['question' => 'What happens if a connected service goes down?', 'answer' => 'Our integrations queue and retry work, and alert you if something needs attention, so data is not lost.'],
            ['question' => 'Do you document the APIs you build?', 'answer' => 'Yes. Every API comes with clear documentation for your team and partners.'],
        ],
    ],
    [
        'title' => 'CMS & Content Platforms',
        'slug' => 'cms-content-platforms',
        'icon' => 'document',
        'tagline' => 'Publish and manage content without waiting on developers.',
        'summary' => 'Flexible and easy-to-manage content solutions for your team.',
        'technologies' => ['Laravel', 'Filament', 'MySQL', 'AWS'],
        'body' => '<p>Your team should be able to publish pages, articles and media on their own schedule. We build content platforms that make publishing simple while keeping your brand and site structure consistent.</p><h2>Structured, flexible content</h2><p>Reusable content blocks, media libraries and clear editorial workflows let editors build rich pages without breaking the design.</p><h2>SEO and performance built in</h2><p>Meta tags, structured data, sitemaps and fast page delivery are part of the platform, so every page you publish is ready to be found.</p>',
        'highlights' => [
            ['title' => 'Custom admin panels', 'description' => 'Editing screens designed around your content.'],
            ['title' => 'Media library', 'description' => 'Upload, organize and reuse images, video and documents.'],
            ['title' => 'Roles & workflows', 'description' => 'Drafts, scheduling and permissions for your team.'],
            ['title' => 'SEO tooling', 'description' => 'Per-page meta, social previews and sitemaps.'],
        ],
        'faqs' => [
            ['question' => 'Why not just use WordPress?', 'answer' => 'WordPress is right for some projects. A custom content platform can be faster, more secure and tailored to your exact content model — we will help you choose.'],
            ['question' => 'Can you migrate our existing content?', 'answer' => 'Yes. We migrate pages, posts and media and keep URLs or set up redirects to protect search rankings.'],
            ['question' => 'Will our team get training?', 'answer' => 'Yes. We walk your editors through the platform and provide simple documentation.'],
        ],
    ],
    [
        'title' => 'AI Development',
        'slug' => 'ai-development',
        'icon' => 'sparkles',
        // Showcased in the Specialised Expertise spotlight, not the main homepage grid.
        'is_featured' => false,
        'tagline' => 'Practical AI that saves time and creates new value for your business.',
        'summary' => 'AI assistants, automation and LLM integrations built into your products and workflows.',
        'technologies' => ['Python', 'FastAPI', 'OpenAI API', 'Claude API', 'LangChain', 'Vector databases'],
        'body' => '<p>AI is most valuable when it solves a specific business problem. We help you find those opportunities and turn them into reliable features: assistants that answer customer questions, automations that remove repetitive work, and tools that make your data easier to use.</p><h2>From idea to production</h2><p>We start with a focused use case, build a working prototype quickly, and measure it against real examples before rolling it out. Every solution is designed with data privacy, cost control and human oversight in mind.</p><h2>Built into what you already use</h2><p>We integrate AI into your existing website, apps and back-office systems through secure APIs, so your team gets the benefits without changing how they work.</p>',
        'highlights' => [
            ['title' => 'AI assistants & chatbots', 'description' => 'Answer customer and team questions using your own content.'],
            ['title' => 'Workflow automation', 'description' => 'Classify, summarise and route documents and requests automatically.'],
            ['title' => 'LLM integrations', 'description' => 'Add AI features to your website, apps and internal tools.'],
            ['title' => 'Responsible AI', 'description' => 'Privacy-aware design, guardrails, testing and cost monitoring.'],
        ],
        'faqs' => [
            ['question' => 'Where should we start with AI?', 'answer' => 'With one well-defined problem — for example, answering common customer questions or processing incoming documents. We help you pick a use case with clear value and build from there.'],
            ['question' => 'Is our data safe when using AI services?', 'answer' => 'We design integrations to share only the data needed, use providers\' business terms that exclude training on your data where available, and keep sensitive information protected.'],
            ['question' => 'Can AI be added to our existing website or software?', 'answer' => 'Yes. Most AI features are added through APIs, so they can be integrated into the systems you already have.'],
        ],
    ],
    [
        'title' => 'Digital Marketing',
        'slug' => 'digital-marketing',
        'icon' => 'megaphone',
        // Showcased in the Specialised Expertise spotlight, not the main homepage grid.
        'is_featured' => false,
        'tagline' => 'Get found by the right customers — and turn visits into leads.',
        'summary' => 'SEO, content, paid campaigns and analytics that grow your reach and conversions.',
        'technologies' => ['Google Analytics 4', 'Google Search Console', 'Google Ads', 'Meta Ads', 'Google Tag Manager'],
        'body' => '<p>A great website only works if the right people find it. We combine technical know-how with marketing to help your business get discovered, earn trust and convert visitors into enquiries and sales.</p><h2>Built on a strong technical foundation</h2><p>Because we build websites, we fix the technical issues that hold rankings back — speed, structure, structured data and indexing — before investing in content and campaigns.</p><h2>Measured, not guessed</h2><p>We set up clear tracking and reporting so you can see which channels bring results, and we keep improving what works.</p>',
        'highlights' => [
            ['title' => 'Search engine optimisation', 'description' => 'Technical SEO, on-page optimisation and local search.'],
            ['title' => 'Content marketing', 'description' => 'Helpful articles and landing pages that attract and convert.'],
            ['title' => 'Paid campaigns', 'description' => 'Search and social ad campaigns focused on qualified leads.'],
            ['title' => 'Analytics & conversion', 'description' => 'Tracking, reporting and continuous conversion improvements.'],
        ],
        'faqs' => [
            ['question' => 'How long does SEO take to show results?', 'answer' => 'Technical fixes can help quickly, but meaningful ranking improvements usually build over several months. We agree clear goals and report progress regularly.'],
            ['question' => 'Do you run paid advertising?', 'answer' => 'Yes. We plan, launch and optimise search and social campaigns, with your ad budget paid directly to the platforms.'],
            ['question' => 'Will we be able to see the results?', 'answer' => 'Yes. We set up analytics and share simple reports showing traffic, leads and what is driving them.'],
        ],
    ],
];
