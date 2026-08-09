"""The canonical CrewAI + ASES integration — matches 04-public-api.md and
public-api-contract.md (section 7) verbatim. Requires a real LLM provider
to actually run crew.kickoff() — see demo_without_llm.py in this same
folder for a version that needs no external services at all.
"""

from crewai import Agent, Crew, Task
from ases import ASES
from ases.adapters import CrewAIAdapter

researcher = Agent(
    role="Security Researcher",
    goal="Investigate recent AI Agent security incidents",
    backstory="An experienced security researcher tracking emerging AI Agent threats.",
)

investigate_task = Task(
    description="Summarize the most significant AI Agent security incident from the last month.",
    expected_output="A concise, three-sentence summary.",
    agent=researcher,
)

crew = Crew(agents=[researcher], tasks=[investigate_task])

# --- ASES integration: exactly the documented three additional lines ---
ases = ASES(api_key="ases_xxxxxxxxx")  # or rely on the ASES_API_KEY env var
adapter = CrewAIAdapter(crew)
ases.attach(adapter)
ases.start()

result = crew.kickoff()

ases.stop()
# ------------------------------------------------------------------------

print(result)
