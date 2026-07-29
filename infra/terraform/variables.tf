variable "aws_region" {
  type    = string
  default = "eu-south-2"
}

variable "project_name" {
  type    = string
  default = "zentrum"
}

variable "environment" {
  type    = string
  default = "production"
}

variable "domain_name" {
  type    = string
  default = "zentrum-tvde.com"
}

variable "certificate_arn" {
  description = "Validated ACM certificate ARN for the production domain and www name."
  type        = string
}

variable "image_tag" {
  description = "Existing immutable ECR tag used to create the first ECS task definitions."
  type        = string
}

variable "github_repository" {
  type    = string
  default = "marcosborg/ZentrumTask"
}

variable "app_secret_values" {
  description = "Additional Laravel secrets merged into the generated database and APP_KEY values."
  type        = map(string)
  sensitive   = true
  default     = {}
}

variable "db_instance_class" {
  type    = string
  default = "db.t4g.micro"
}
