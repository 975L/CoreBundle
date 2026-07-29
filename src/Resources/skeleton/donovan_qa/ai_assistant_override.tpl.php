{# Adds this app's self-hosted Donovan backend; MUST extend the base file, never ai_assistant.html.twig itself #}
{% extends '@c975LUi/management/_ai_assistant_base.html.twig' %}

{% block extra_backends %}
    {# Same bar as the section above: a backend several sites may call into stays narrowly visible #}
    {% if is_granted('ROLE_SUPER_ADMIN') %}
        <h2 class="mt-4">Donovan (Q&amp;A) — backend</h2>
        <p class="text-muted">Answers "which block should I use" for every site pointed at it - see <code>DonovanQaController</code>.</p>

        {% set missing = donovan_qa_missing_slugs() %}
        {% set links = donovan_qa_config_links() %}
        {% if not donovan_qa_enabled() %}
            <ul>
                {% if 'donovan-qa-llm-enabled' in missing %}
                    <li><a href="{{ links['donovan-qa-llm-enabled'] }}">enable the backend</a></li>
                {% endif %}
                {% if 'donovan-qa-llm-provider' in missing %}
                    <li><a href="{{ links['donovan-qa-llm-provider'] }}">choose the LLM provider (anthropic or euria)</a></li>
                {% endif %}
                {% if 'donovan-qa-llm-api-key' in missing %}
                    <li><a href="{{ links['donovan-qa-llm-api-key'] }}">set the API key</a></li>
                {% endif %}
                {% if 'donovan-qa-llm-model' in missing %}
                    <li><a href="{{ links['donovan-qa-llm-model'] }}">set the model (mandatory for Euria)</a></li>
                {% endif %}
                {% if 'donovan-qa-llm-base-uri' in missing %}
                    <li><a href="{{ links['donovan-qa-llm-base-uri'] }}">set the base URI (mandatory for Euria)</a></li>
                {% endif %}
            </ul>
        {% else %}
            <p class="text-success">✓ LLM backend operational.</p>
        {% endif %}

        <h3 class="h6 mt-3">Sites authorized to call this backend</h3>
        {% if 'donovan-qa-authorized-tokens' in missing %}
            <p class="text-muted small">
                No authorized site yet -
                <a href="{{ links['donovan-qa-authorized-tokens'] }}">add at least one token</a>.
                Without it, every call from a client site fails with 401, even once the LLM backend above is operational.
            </p>
        {% else %}
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Site</th>
                            <th>Token</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for site, token in donovan_qa_authorized_tokens() %}
                            <tr>
                                <td>{{ site }}</td>
                                <td><code>{{ token }}</code></td>
                            </tr>
                        {% endfor %}
                    </tbody>
                </table>
            </div>
            <p class="text-muted small">
                <a href="{{ links['donovan-qa-authorized-tokens'] }}">Edit</a> - each site must have the exact same value in its own <code>ui-ai-assistant-dashboard-token</code>.
            </p>
        {% endif %}
    {% endif %}
{% endblock %}
