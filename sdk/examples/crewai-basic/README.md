# Example — CrewAI Integration

The canonical V1 integration, matching `04-public-api.md` and
`public-api-contract.md` (section 7) verbatim.

## Files

- [`real_crew_example.py`](./real_crew_example.py) — the exact, complete
  integration pattern for a real CrewAI `Crew`. Requires an LLM provider
  (e.g. `OPENAI_API_KEY` set, or a local model via Ollama/LM Studio — see
  `07-agent-framework-ecosystem.md`, section 4) to actually call
  `crew.kickoff()`.
- [`demo_without_llm.py`](./demo_without_llm.py) — runnable with **zero**
  external services. Emits real `crewai` event objects directly onto
  crewai's own event bus (the same mechanism a live `crew.kickoff()` run
  uses internally) so you can see the exact Observation JSON ASES would
  send, without needing an LLM API key.

## Running the zero-dependency demo

```bash
pip install ases[crewai]
python demo_without_llm.py
```

## Running against a real Crew

```bash
pip install ases[crewai]
export OPENAI_API_KEY=sk-...
export ASES_API_KEY=ases_xxxxxxxxx
python real_crew_example.py
```
