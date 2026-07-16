# Test Log
# UKMart User Management System
## Automated Testing with Cypress

**Version**: 1.0.0  
**Date**: [Insert Date - e.g., 28/12/2024]

---

## Document Control

| Field | Details |
|-------|---------|
| **Document Name** | UKMart Test Log (Iteration 1) |
| **Reference Number** | UKMART_TL_1 |
| **Version** | 1.0.0 |
| **Project Code** | UKMART_CYPRESS |
| **Status** | In-use |
| **Date Released** | [Insert Date] |

### Signatures

| Name | Position | Signature | Date |
|------|----------|-----------|------|
| **Prepared By:** | [Your Name] | Test Analyst | [Date] |
| **Reviewed By:** | [Reviewer Name] | Test Lead | [Date] |
| **Verified By:** | [Verifier Name] | Test Manager | [Date] |

---

## Version History

| Version | Release Date | Section | Amendments |
|---------|--------------|---------|------------|
| 1.0.0 | [Date] | All | Original document |

---

## Distribution List

| Version | Release Date | Controlled Copy No | Recipient Name | Department | Issue Date | Return Date |
|---------|--------------|-------------------|----------------|------------|------------|-------------|
| 1.0.0 | [Date] | 01 | QA Department | Testing Team | [Date] | - |
| 1.0.0 | [Date] | 02 | Development Team | Dev Team | [Date] | - |

---

## Test Log

### General Information

| Field | Details |
|-------|---------|
| **Test Log Scope** | This Test Log covers User Registration and Login functionality as described in Test Plan |
| **Test Log Description** | The items tested were user registration and login features in UKMart system. This test log records the execution of automated test procedures using Cypress. |
| **Version** | 1.0.0 |
| **Author** | [Your Name] |
| **Contact Number** | [Your Contact] |
| **Revision Version** | 1.0 |
| **People Responsible** | [List team members] |

### Activities Execution Information

| Field | Details |
|-------|---------|
| **Execution Start Date** | [Date] |
| **Execution End Date** | [Date] |
| **Execution Start Time** | [Time] |
| **Execution End Time** | [Time] |
| **Tester Name** | [Your Name] |
| **Participants** | [List participants] |

---

## Procedure Results

| Requirement ID | Test Design ID | Test Case ID | Test Procedure ID | Type of Testing | Tool | Pass/Fail | Test Incident Report ID | Remark |
|----------------|----------------|--------------|-------------------|-----------------|------|-----------|------------------------|---------|
| REQ_REG_001 | TDS-REG-01 | TC-001 | TP-REG-001 | Functional | Cypress | Pass | - | User registration successful |
| REQ_REG_002 | TDS-REG-01 | TC-002 | TP-REG-002 | Functional | Cypress | Pass | - | Multiple users registered |
| REQ_REG_003 | TDS-REG-01 | TC-003 | TP-REG-003 | Functional | Cypress | Pass | - | Duplicate email rejected |
| REQ_LOGIN_001 | TDS-LOGIN-01 | TC-004 | TP-LOGIN-001 | Functional | Cypress | Pass | - | Valid login successful |
| REQ_LOGIN_002 | TDS-LOGIN-01 | TC-005 | TP-LOGIN-002 | Functional | Cypress | Pass | - | Multiple users login |
| REQ_LOGIN_003 | TDS-LOGIN-01 | TC-006 | TP-LOGIN-003 | Functional | Cypress | Pass | - | Invalid password rejected |
| REQ_LOGIN_004 | TDS-LOGIN-01 | TC-007 | TP-LOGIN-004 | Functional | Cypress | Pass | - | Non-existent email rejected |
| REQ_LOGIN_005 | TDS-LOGIN-01 | TC-008 | TP-LOGIN-005 | Functional | Cypress | Pass | - | Empty fields rejected |
| REQ_INT_001 | TDS-INT-01 | TC-009 | TP-INT-001 | Integration | Cypress | Pass | - | Complete user journey successful |

---

## Environment Information

### Requested Test Environment

| Component | Details |
|-----------|---------|
| **Hardware** | Desktop PC / Laptop |
| **Operating System** | Windows 11 |
| **Browser** | Chrome (Electron) |
| **Test Tool** | Cypress v13.x |
| **Base URL** | http://lrgs.ftsm.ukm.my/users/a201430/UKMart |
| **Test Data** | Mockaroo JSON file (users.json) |

### Test Environment After Changes

No changes made to test environment.

---

## Anomalous Events

| Unexpected Event Occurred | Test Procedure ID | Anomaly Reporter Name | Date | Resolution |
|--------------------------|-------------------|----------------------|------|------------|
| [None / Describe any issues] | - | - | - | - |

---

## Test Summary

### Overall Results

| Metric | Count | Percentage |
|--------|-------|------------|
| **Total Test Cases** | 9 | 100% |
| **Passed** | [X] | [X]% |
| **Failed** | [X] | [X]% |
| **Blocked** | 0 | 0% |
| **Not Executed** | 0 | 0% |

### Test Coverage

| Feature | Test Cases | Passed | Failed | Coverage |
|---------|------------|--------|--------|----------|
| User Registration | 3 | [X] | [X] | [X]% |
| User Login | 5 | [X] | [X] | [X]% |
| Integration | 1 | [X] | [X] | [X]% |

---

## Defects Summary

| Defect ID | Test Case ID | Severity | Status | Description | Reported By | Date |
|-----------|--------------|----------|--------|-------------|-------------|------|
| [None / List defects] | - | - | - | - | - | - |

---

## Attachments

1. Cypress Test Reports (HTML) - `cypress/reports/`
2. Test Execution Videos - `cypress/videos/`
3. Test Screenshots - `cypress/screenshots/`
4. Test Data File - `cypress/fixtures/users.json`
5. Test Scripts - `cypress/e2e/register-and-login.cy.js`

---

## Notes and Comments

- All tests were executed using automated testing tool Cypress
- Test data generated from Mockaroo with realistic user information
- Tests performed on live development environment
- No critical defects found during this test iteration

---

## Approval

| Role | Name | Signature | Date |
|------|------|-----------|------|
| **Test Analyst** | [Your Name] | __________ | [Date] |
| **Test Lead** | [Reviewer Name] | __________ | [Date] |
| **Test Manager** | [Manager Name] | __________ | [Date] |

---

**End of Test Log**