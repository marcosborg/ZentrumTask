output "terraform_state_bucket" {
  value = aws_s3_bucket.terraform_state.id
}

output "github_infrastructure_role_arn" {
  value = aws_iam_role.github_infrastructure.arn
}
